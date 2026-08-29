<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Master Switch
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable all analytics tracking.
    | Useful for local development or emergency kill switch.
    |
    */
    'enabled' => filter_var(env('POSTHOG_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | PostHog Configuration
    |--------------------------------------------------------------------------
    |
    | The key should be your public project API key (write-only), not your
    | private API key.
    |
    */
    'posthog' => [
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://us.i.posthog.com'),
        'autocapture' => filter_var(env('POSTHOG_AUTOCAPTURE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => filter_var(env('POSTHOG_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
];
