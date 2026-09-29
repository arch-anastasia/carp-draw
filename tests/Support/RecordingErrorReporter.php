<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\ErrorReporter;
use Throwable;

/**
 * An ErrorReporter that records instead of reporting.
 *
 * Sentry and PostHog are not reachable from a test run, and must not be. This
 * stands in for both so a test can assert the one thing that matters: that a
 * failure was reported at all, with enough context to act on and none of the
 * photograph behind it.
 *
 * Bind it over the real one:
 *
 *     $reporter = new RecordingErrorReporter(app(AnalyticsService::class));
 *     $this->app->instance(ErrorReporter::class, $reporter);
 */
class RecordingErrorReporter extends ErrorReporter
{
    /**
     * Every call, in order.
     *
     * @var array<int, array{kind: string, code: string, message: string, context: array<string, mixed>, user: User|null}>
     */
    public array $calls = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function report(Throwable $e, string $code, array $context = [], ?User $user = null, ?string $distinctId = null): void
    {
        $this->record('exception', $code, $e->getMessage(), $context, $user);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reportIssue(string $code, string $message, array $context = [], ?User $user = null, ?string $distinctId = null): void
    {
        $this->record('issue', $code, $message, $context, $user);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warn(string $code, string $message, array $context = [], ?User $user = null): void
    {
        $this->record('warn', $code, $message, $context, $user);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function breadcrumb(string $category, string $message, array $context = []): void
    {
        $this->record('breadcrumb', $category, $message, $context, null);
    }

    /**
     * The first call with this code, or null.
     *
     * @return array{kind: string, code: string, message: string, context: array<string, mixed>, user: User|null}|null
     */
    public function firstFor(string $code): ?array
    {
        foreach ($this->calls as $call) {
            if ($call['code'] === $code) {
                return $call;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(string $kind, string $code, string $message, array $context, ?User $user): void
    {
        $this->calls[] = [
            'kind' => $kind,
            'code' => $code,
            'message' => $message,
            'context' => $context,
            'user' => $user,
        ];
    }
}
