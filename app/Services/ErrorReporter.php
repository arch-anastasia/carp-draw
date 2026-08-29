<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;

/**
 * The one place a failure is reported from.
 *
 * Every error the product can produce ends up here, and here it fans out to
 * three destinations that fail independently:
 *
 *   Sentry   - the stack, the tags, the breadcrumbs. Where an engineer goes.
 *   PostHog  - the same failure as an `$exception` event, attributed to the
 *              account that hit it, so "which users are affected" and "what
 *              did they do next" are one query away from the funnel.
 *   The log  - the witness of last resort, written first and unconditionally,
 *              so a failure survives both vendors being unreachable.
 *
 * Nothing in this class may throw. A reporter that crashes turns one error
 * into two and hides the first, which is the exact failure it exists to
 * prevent - so every leg is individually guarded.
 *
 * ## Scrubbing
 *
 * This application moves photographs of people's faces through jobs, request
 * bodies and provider payloads. `REDACTED_KEYS` is not a nicety; a render
 * context that carried `imageContents` would ship a base64 selfie to two
 * third parties. Values are also length-capped, because the way a face
 * usually escapes is as an unremarkable-looking string that happens to be
 * 400KB long.
 */
class ErrorReporter
{
    /**
     * Context keys whose VALUE is never sent anywhere. Matched loosely
     * (case-insensitive substring) so `photo`, `source_photo` and
     * `photoBase64` are all covered by one entry.
     *
     * @var array<int, string>
     */
    private const REDACTED_KEYS = [
        'photo', 'image', 'selfie', 'reference', 'contents', 'base64',
        'password', 'secret', 'token', 'authorization', 'api_key', 'apikey',
        'prompt', 'description', 'email', 'card', 'cvc', 'iban',
    ];

    /**
     * Longest string we forward. A provider's error body is worth reading; a
     * data URI is not, and the two are indistinguishable by key name alone.
     */
    private const MAX_VALUE_LENGTH = 500;

    public function __construct(private AnalyticsService $analytics) {}

    /**
     * Report a thrown error.
     *
     * `$code` is the stable, human-chosen name for this failure ('render_failed',
     * 'stripe_refund_failed'). It becomes the Sentry tag and the PostHog
     * property that dashboards are built on, so it must not carry an id or a
     * count - anything variable belongs in `$context`.
     *
     * @param  array<string, mixed>  $context
     */
    public function report(Throwable $e, string $code, array $context = [], ?User $user = null, ?string $distinctId = null): void
    {
        $safe = $this->scrub($context);

        $this->log('error', $code, $e->getMessage(), $safe);
        $this->toSentry($code, $safe, $user, Severity::error(), $e);
        $this->toPostHog($code, $safe, $user, $distinctId, $e);
    }

    /**
     * Report a failure that has no exception object.
     *
     * A provider that answers "no" is not a crash, but it is still something a
     * person hit and did not get what they paid for. These are reported at the
     * same volume and into the same streams as thrown errors - the difference
     * between the two is an implementation detail of the provider's API, not
     * of the user's experience.
     *
     * @param  array<string, mixed>  $context
     */
    public function reportIssue(string $code, string $message, array $context = [], ?User $user = null, ?string $distinctId = null): void
    {
        $safe = $this->scrub($context);

        $this->log('error', $code, $message, $safe);
        $this->toSentry($code, $safe, $user, Severity::error(), null, $message);
        $this->toPostHog($code, $safe, $user, $distinctId, null, $message);
    }

    /**
     * Report something that has not failed yet but is on its way there - a
     * retryable provider error, a degraded fallback, a refused third party.
     *
     * Warnings reach Sentry and the log but NOT PostHog error tracking: a
     * render that retries once and then succeeds is not an issue anybody
     * needs paging on, and filing it as one would drown the failures that
     * are. The retry still gets its own product event at the call site.
     *
     * @param  array<string, mixed>  $context
     */
    public function warn(string $code, string $message, array $context = [], ?User $user = null): void
    {
        $safe = $this->scrub($context);

        $this->log('warning', $code, $message, $safe);
        $this->toSentry($code, $safe, $user, Severity::warning(), null, $message);
    }

    /**
     * Leave a trail on the current scope without reporting anything.
     *
     * Read only when something later fails, which is what makes it the right
     * home for the noisy detail - a CLI's exit code, a provider's retry count
     * - that would be spam as its own event and is the first thing anyone
     * wants when the render finally gives up.
     *
     * @param  array<string, mixed>  $context
     */
    public function breadcrumb(string $category, string $message, array $context = []): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        try {
            \Sentry\addBreadcrumb(new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                $category,
                $message,
                $this->scrub($context),
            ));
        } catch (Throwable) {
            // A breadcrumb is a convenience. It must never cost a request.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function toSentry(
        string $code,
        array $context,
        ?User $user,
        Severity $severity,
        ?Throwable $e = null,
        ?string $message = null,
    ): void {
        if (! app()->bound('sentry')) {
            return;
        }

        try {
            \Sentry\withScope(function (Scope $scope) use ($code, $context, $user, $severity, $e, $message): void {
                $scope->setTag('error_code', $code);
                $scope->setLevel($severity);

                /*
                 * Tags are the indexed, filterable half of a Sentry event.
                 * These answer the questions asked before anyone opens the
                 * stack: which backend, which subsystem, which flavour of
                 * failure, was it worth retrying.
                 */
                foreach (['provider', 'subsystem', 'failure_code', 'retryable'] as $key) {
                    if (isset($context[$key]) && is_scalar($context[$key])) {
                        $scope->setTag($key, (string) $context[$key]);
                    }
                }

                if ($user !== null) {
                    // The id, and only the id. send_default_pii is off for a
                    // reason and this must not quietly undo it.
                    $scope->setUser(['id' => (string) $user->id]);
                }

                $scope->setContext('failure', $context);

                /*
                 * Group by our own code rather than by the stack. Two renders
                 * that die in different frames of the same provider are one
                 * issue to whoever is on call, and Sentry's default grouping
                 * would file them as two.
                 *
                 * `failure_code` splits that group back apart along the line
                 * that matters: a photo the safety filter refused and a
                 * provider that is down are both `render_failed`, but one is
                 * a support reply and the other is a page.
                 */
                $fingerprint = ['{{ default }}', $code];

                if (isset($context['failure_code']) && is_scalar($context['failure_code'])) {
                    $fingerprint[] = (string) $context['failure_code'];
                }

                $scope->setFingerprint($fingerprint);

                if ($e !== null) {
                    \Sentry\captureException($e);

                    return;
                }

                \Sentry\captureMessage($message ?? $code, $severity);
            });
        } catch (Throwable $failure) {
            Log::warning('Failed to report to Sentry', [
                'error_code' => $code,
                'error' => $failure->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function toPostHog(
        string $code,
        array $context,
        ?User $user,
        ?string $distinctId,
        ?Throwable $e = null,
        ?string $message = null,
    ): void {
        $properties = array_merge($context, ['error_code' => $code]);

        if ($e !== null) {
            $this->analytics->captureException($e, $user, $properties, $distinctId);

            return;
        }

        $this->analytics->captureIssue($code, $message ?? $code, $user, $properties, $distinctId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $code, string $message, array $context): void
    {
        Log::log($level, "[{$code}] {$message}", array_merge($context, [
            /*
             * Read by ReportLoggedErrors, which forwards stray Log::error()
             * calls to Sentry. This line has already been reported in full;
             * the marker is what stops it arriving twice.
             */
            'reported' => true,
        ]));
    }

    /**
     * Strip anything that could carry a face, a credential or an essay.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function scrub(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if ($this->isRedacted((string) $key)) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->scrub($value);

                continue;
            }

            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$key] = $value;

                continue;
            }

            if (! is_scalar($value)) {
                $safe[$key] = '['.get_debug_type($value).']';

                continue;
            }

            $safe[$key] = $this->truncate((string) $value);
        }

        return $safe;
    }

    private function isRedacted(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::REDACTED_KEYS as $redacted) {
            if (str_contains($needle, $redacted)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value): string
    {
        /*
         * A long unbroken run of base64 is a photograph however it got here -
         * a data URI, a provider echoing the request back, a var_export in an
         * exception message. Dropped rather than truncated: the first 500
         * bytes of a face are still a face's worth of nothing useful.
         */
        if (Str::length($value) > self::MAX_VALUE_LENGTH && preg_match('#[A-Za-z0-9+/=]{400,}#', $value) === 1) {
            return '[binary omitted]';
        }

        return Str::limit($value, self::MAX_VALUE_LENGTH);
    }
}
