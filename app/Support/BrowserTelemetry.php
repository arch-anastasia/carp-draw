<?php

namespace App\Support;

class BrowserTelemetry
{
    /** @return array{dsn: ?string, environment: ?string, release: ?string, tracesSampleRate: float, ignoreErrors: list<string>} */
    public static function config(): array
    {
        return [
            'dsn' => config('sentry.browser.dsn'),
            'environment' => config('sentry.browser.environment') ?? app()->environment(),
            'release' => config('sentry.browser.release'),
            'tracesSampleRate' => max(0, min(1, (float) config('sentry.browser.traces_sample_rate', 0))),
            'ignoreErrors' => [],
        ];
    }
}
