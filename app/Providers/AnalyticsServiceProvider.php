<?php

namespace App\Providers;

use App\Services\AnalyticsService;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AnalyticsService::class, function () {
            return new AnalyticsService;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Flush PostHog events at the end of the request
        // Register callback unconditionally - check resolution inside the callback
        $this->app->terminating(function () {
            if ($this->app->resolved(AnalyticsService::class)) {
                app(AnalyticsService::class)->flush();
            }
        });
    }
}
