<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PostHog\PostHog;
use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;

class AnalyticsService
{
    /**
     * The distinct_id server-side errors are filed under when there is no one
     * to attribute them to - a queue worker, a scheduled command, a webhook.
     *
     * Paired with `$process_person_profile: false` at every call site that
     * uses it: without that, every unattributable crash in the product would
     * pile onto one enormous person profile and PostHog would bill for it.
     */
    private const UNATTRIBUTED_DISTINCT_ID = 'server';

    /** Deepest stack we send. Past this a trace is noise, and it is billed. */
    private const MAX_STACK_FRAMES = 40;

    private bool $initialized = false;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize the PostHog client.
     */
    private function initialize(): void
    {
        if (! config('analytics.enabled') || ! config('analytics.posthog.key')) {
            return;
        }

        try {
            PostHog::init(
                config('analytics.posthog.key'),
                ['host' => config('analytics.posthog.host')]
            );
            $this->initialized = true;
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to initialize PostHog', $e);
        }
    }

    /**
     * Check if analytics is enabled and initialized.
     */
    public function isEnabled(): bool
    {
        return $this->initialized;
    }

    /**
     * Track an event for a user.
     *
     * @param  array<string, mixed>  $properties
     */
    public function track(User $user, string $event, array $properties = []): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            PostHog::capture([
                'distinctId' => $user->id,
                'event' => $event,
                'properties' => array_merge($properties, [
                    'source' => 'server',
                    'timestamp' => now()->toISOString(),
                ]),
            ]);
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to track event', $e, ['event' => $event]);
        }
    }

    /**
     * Track an event using a raw PostHog distinct_id (e.g. read from the
     * `ph_{api_key}_posthog` cookie). Used when the action happens server-side
     * before the user is authenticated, so we can attribute it to the right
     * anonymous person profile that PostHog will later merge on identify().
     *
     * @param  array<string, mixed>  $properties
     */
    public function trackByDistinctId(string $distinctId, string $event, array $properties = []): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            PostHog::capture([
                'distinctId' => $distinctId,
                'event' => $event,
                'properties' => array_merge($properties, [
                    'source' => 'server',
                    'timestamp' => now()->toISOString(),
                ]),
            ]);
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to track event by distinct_id', $e, ['event' => $event]);
        }
    }

    /**
     * Read the PostHog anonymous distinct_id from the request cookie.
     * Returns null if PostHog isn't enabled, the cookie isn't set, or the
     * cookie value can't be parsed.
     */
    public function getPostHogDistinctIdFromCookie(Request $request): ?string
    {
        $apiKey = config('analytics.posthog.key');

        if (! $apiKey) {
            return null;
        }

        $raw = $request->cookie("ph_{$apiKey}_posthog");

        if (! $raw) {
            return null;
        }

        // json_decode returns null on invalid input (no exceptions without
        // JSON_THROW_ON_ERROR), so the is_array guard is sufficient.
        $data = json_decode($raw, true);

        return is_array($data) ? ($data['distinct_id'] ?? null) : null;
    }

    /**
     * Track an event for an anonymous user by email hash.
     *
     * @param  array<string, mixed>  $properties
     */
    public function trackAnonymous(string $email, string $event, array $properties = []): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            PostHog::capture([
                'distinctId' => $this->hashEmail($email),
                'event' => $event,
                'properties' => array_merge($properties, [
                    'source' => 'server',
                    'timestamp' => now()->toISOString(),
                ]),
            ]);
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to track anonymous event', $e, ['event' => $event]);
        }
    }

    /**
     * Identify a user with properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function identify(User $user, array $properties = []): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            PostHog::identify([
                'distinctId' => $user->id,
                'properties' => array_merge([
                    'email' => $user->email,
                    'plan' => $this->getUserPlan($user),
                    'signed_up_at' => $user->created_at?->toISOString(),
                ], $properties),
            ]);
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to identify user', $e);
        }
    }

    /**
     * Alias an anonymous ID to a user ID (for linking pre-signup activity).
     */
    public function alias(User $user, string $anonymousId): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            PostHog::alias([
                'distinctId' => $user->id,
                'alias' => $anonymousId,
            ]);
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to alias user', $e);
        }
    }

    /**
     * Flush any pending events to PostHog.
     * Call this at the end of request lifecycle if needed.
     */
    public function flush(): void
    {
        if (! $this->initialized) {
            return;
        }

        try {
            $this->assertDelivered(PostHog::flush(), 'flush');
        } catch (Throwable $e) {
            $this->reportOwnFailure('Failed to flush PostHog events', $e);
        }
    }

    /**
     * posthog-php does not throw when a batch fails to reach PostHog - it
     * carries on and returns a falsy value. That is a silent hole in exactly
     * the system this class exists to make un-silent: an outage, a wrong host
     * or a revoked key would drop every error event we sent, and read from
     * here as a clean run.
     *
     * Checked loosely, and it has to be. `flush()` does not return a bool: it
     * hands back whatever the last batch's HTTP response body was, so success
     * is a JSON string and failure is `null` - never `false`. A strict
     * comparison against `false` looks correct, passes review, and detects
     * nothing.
     *
     * Sentry is the independent witness, which is the whole reason there are
     * two destinations rather than one.
     *
     * @param  array<string, mixed>  $context
     */
    private function assertDelivered(mixed $result, string $stage, array $context = []): void
    {
        if ($result) {
            return;
        }

        Log::warning('PostHog did not accept a batch', array_merge($context, ['stage' => $stage]));

        if (! app()->bound('sentry')) {
            return;
        }

        try {
            \Sentry\withScope(function (Scope $scope) use ($stage, $context): void {
                $scope->setTag('subsystem', 'analytics');
                $scope->setLevel(Severity::warning());
                $scope->setContext('analytics_failure', array_merge($context, ['stage' => $stage]));
                \Sentry\captureMessage('PostHog did not accept a batch', Severity::warning());
            });
        } catch (Throwable) {
            // Both vendors unreachable. The log line above is all there is.
        }
    }

    /**
     * Send a thrown error to PostHog error tracking.
     *
     * PostHog does not read ordinary events as errors. Its issue grouping is
     * driven by the `$exception` event and the `$exception_list` shape below,
     * which is why this does not just call track() with an 'error' name - an
     * event called `render_failed` is a funnel metric, and this is the crash
     * behind it. Both are wanted, so both are sent.
     *
     * @param  array<string, mixed>  $properties
     */
    public function captureException(Throwable $e, ?User $user = null, array $properties = [], ?string $distinctId = null): void
    {
        $this->captureExceptionPayload(
            type: $e::class,
            value: $e->getMessage(),
            frames: $this->stackFrames($e),
            user: $user,
            distinctId: $distinctId,
            properties: $properties,
            synthetic: false,
        );
    }

    /**
     * Send a failure that never had an exception object.
     *
     * A render refused by the provider's safety filter is a real issue the
     * visitor hit, but it arrives as a status code rather than a throwable.
     * Reporting it as a synthetic exception puts it in the same issue stream
     * as the crashes instead of leaving it as a metric nobody watches.
     *
     * @param  array<string, mixed>  $properties
     */
    public function captureIssue(string $type, string $message, ?User $user = null, array $properties = [], ?string $distinctId = null): void
    {
        $this->captureExceptionPayload(
            type: $type,
            value: $message,
            frames: [],
            user: $user,
            distinctId: $distinctId,
            properties: $properties,
            synthetic: true,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $frames
     * @param  array<string, mixed>  $properties
     */
    private function captureExceptionPayload(
        string $type,
        string $value,
        array $frames,
        ?User $user,
        ?string $distinctId,
        array $properties,
        bool $synthetic,
    ): void {
        if (! $this->initialized) {
            return;
        }

        $resolvedId = $user !== null
            ? $user->id
            : ($distinctId ?? self::UNATTRIBUTED_DISTINCT_ID);

        $exception = [
            'type' => $type,
            'value' => $value,
            'mechanism' => [
                'handled' => true,
                'synthetic' => $synthetic,
            ],
        ];

        if ($frames !== []) {
            $exception['stacktrace'] = ['type' => 'raw', 'frames' => $frames];
        }

        $payload = array_merge($properties, [
            '$exception_list' => [$exception],
            '$exception_type' => $type,
            '$exception_message' => $value,
            '$exception_source' => 'server',
            'source' => 'server',
            'timestamp' => now()->toISOString(),
        ]);

        /*
         * Only skip person processing for the catch-all bucket. A crash we CAN
         * pin to an account should reach that account's profile - "which users
         * hit this" is the first question support asks.
         */
        if ($resolvedId === self::UNATTRIBUTED_DISTINCT_ID) {
            $payload['$process_person_profile'] = false;
        }

        try {
            PostHog::capture([
                'distinctId' => $resolvedId,
                'event' => '$exception',
                'properties' => $payload,
            ]);

            /*
             * Flushed here rather than on request teardown. Most of these are
             * raised from a queue worker, which has no teardown, and an error
             * held in a buffer that dies with the process is an error we never
             * hear about - the exact failure this whole class exists to close.
             */
            $this->assertDelivered(PostHog::flush(), 'exception capture', ['exception_type' => $type]);
        } catch (Throwable $failure) {
            $this->reportOwnFailure('Failed to capture exception in PostHog', $failure, [
                'exception_type' => $type,
            ]);
        }
    }

    /**
     * Flatten a throwable's trace into PostHog frames.
     *
     * Innermost first, paths made relative, `in_app` set so PostHog can fold
     * the vendor frames away. Arguments are deliberately dropped: the studio
     * passes raw image bytes between methods and a trace that carried them
     * would ship somebody's face to an analytics vendor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stackFrames(Throwable $e): array
    {
        $base = base_path().DIRECTORY_SEPARATOR;

        $frames = [[
            'platform' => 'php',
            'filename' => str_replace($base, '', $e->getFile()),
            'abs_path' => $e->getFile(),
            'function' => '<throw>',
            'lineno' => $e->getLine(),
            'in_app' => ! str_contains($e->getFile(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR),
        ]];

        foreach (array_slice($e->getTrace(), 0, self::MAX_STACK_FRAMES) as $frame) {
            $file = (string) ($frame['file'] ?? '[internal]');

            $frames[] = [
                'platform' => 'php',
                'filename' => str_replace($base, '', $file),
                'abs_path' => $file,
                'function' => trim(($frame['class'] ?? '').($frame['type'] ?? '').$frame['function']),
                'lineno' => (int) ($frame['line'] ?? 0),
                'in_app' => $file !== '[internal]'
                    && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR),
            ];
        }

        /*
         * PostHog renders frames outermost-first, the way a PHP trace reads
         * bottom-up. Sending them in getTrace() order puts the throw site at
         * the bottom of the issue page, which is the one line anyone opens it
         * to read.
         */
        return array_reverse($frames);
    }

    /**
     * Report a failure of the analytics pipeline ITSELF.
     *
     * Deliberately not routed through ErrorReporter: that class reports to
     * Sentry AND to PostHog, and telling PostHog that PostHog is down is both
     * futile and a loop. Sentry is the independent witness here, and the log
     * line is the witness of last resort when neither vendor is reachable.
     *
     * @param  array<string, mixed>  $context
     */
    private function reportOwnFailure(string $message, Throwable $e, array $context = []): void
    {
        Log::warning($message, array_merge($context, ['error' => $e->getMessage()]));

        if (! app()->bound('sentry')) {
            return;
        }

        try {
            \Sentry\withScope(function (Scope $scope) use ($e, $message, $context): void {
                $scope->setTag('subsystem', 'analytics');
                $scope->setContext('analytics_failure', array_merge($context, ['stage' => $message]));
                \Sentry\captureException($e);
            });
        } catch (Throwable) {
            // Sentry is down too. The log line above is all we can offer, and
            // an exception raised while reporting must never break the caller.
        }
    }

    /**
     * Hash email for anonymous tracking.
     */
    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower($email).config('app.key'));
    }

    /**
     * Get user's subscription plan.
     */
    private function getUserPlan(User $user): ?string
    {
        if (! $user->subscribed()) {
            return null;
        }

        $subscription = $user->subscription();

        return $subscription !== null ? ($subscription->stripe_price ?? 'subscribed') : 'subscribed';
    }
}
