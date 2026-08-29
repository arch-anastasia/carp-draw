<?php

namespace App\Providers;

use App\Listeners\ReportFailedJobs;
use App\Listeners\ReportLoggedErrors;
use App\Services\AnalyticsService;
use App\Services\ErrorReporter;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Sentry\State\Scope;

/**
 * Wires the two error destinations together and closes the gaps between them.
 *
 * The explicit half - ErrorReporter called from a catch block - lives at the
 * call sites. This provider is the implicit half: the things that must be
 * reported whether or not anybody remembered to write the call.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ErrorReporter::class, function ($app) {
            return new ErrorReporter($app->make(AnalyticsService::class));
        });

        // Built once rather than per log line - see the class comment.
        $this->app->singleton(ReportLoggedErrors::class);
    }

    public function boot(): void
    {
        /*
         * Listener auto-discovery is off for this application (see
         * bootstrap/app.php - it was double-firing every listener), so both of
         * these are registered by hand like the rest.
         */
        Event::listen(MessageLogged::class, ReportLoggedErrors::class);
        Event::listen(JobFailed::class, ReportFailedJobs::class);

        $this->attachUserToSentryScope();
    }

    /**
     * Put the signed-in user's id on every Sentry event for the rest of the
     * request.
     *
     * "How many people hit this" is unanswerable without it, and the SDK will
     * not do it on its own: its own user context is gated behind
     * `send_default_pii`, which is off here because that same switch would
     * also attach the email, the IP and the request body. The id alone is the
     * part worth having.
     */
    private function attachUserToSentryScope(): void
    {
        Event::listen(Authenticated::class, function (Authenticated $event): void {
            if (! $this->app->bound('sentry')) {
                return;
            }

            try {
                \Sentry\configureScope(function (Scope $scope) use ($event): void {
                    $scope->setUser(['id' => (string) $event->user->getAuthIdentifier()]);
                });
            } catch (\Throwable) {
                // Never let instrumentation break a signed-in request.
            }
        });
    }
}
