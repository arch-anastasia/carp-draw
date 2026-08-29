<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Brand Identity
    |--------------------------------------------------------------------------
    |
    | Core brand values used across the application. Blade marketing
    | components, the SEO component and the Inertia shared `branding`
    | prop all read from here so the two surfaces can never disagree.
    |
    */

    'name' => env('APP_NAME', 'CarpDraw AI'),

    'tagline' => env('APP_TAGLINE', 'From a reference to a clear technical drawing.'),

    'support_email' => env('SUPPORT_EMAIL', 'arch.anastasia.ivanova@gmail.com'),

    'support_phone' => env('SUPPORT_PHONE', '+421 918 135 587'),

    // Operational alerts (new paid subscriber, contact messages, etc.).
    // `?:` rather than a default: an env var set to the empty string must
    // fall through to the support address, not silence the alerts.
    'admin_email' => env('ADMIN_NOTIFICATIONS_EMAIL') ?: env('SUPPORT_EMAIL', 'arch.anastasia.ivanova@gmail.com'),
];
