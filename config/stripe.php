<?php

/*
|--------------------------------------------------------------------------
| Stripe Plans
|--------------------------------------------------------------------------
|
| Source of truth for pricing is Stripe itself — StripePricingService
| fetches live unit amounts from the Prices API and caches them for
| `price_cache_ttl_minutes`. Each plan carries a `price_fallback` (cents)
| used when Stripe is unreachable or unconfigured (local dev, CI).
|
| Plans are denominated in CREDITS; every workflow run costs the amount in
| `drawing.credits.actions`. `subscription_name` is what Cashier stores
| against live subscription rows — never rename it once subscribers exist.
|
| `entitlements` is the machine-readable part (ProjectService reads it);
| `features` flows to the plans page payload for display.
|
*/

return [
    'plans' => [
        'maker' => [
            'name' => 'Maker',
            'subscription_name' => 'plans.maker',
            'price_id' => env('STRIPE_MAKER_PRICE_ID'),
            'price_id_yearly' => env('STRIPE_MAKER_PRICE_ID_YEARLY'),
            'price_fallback' => 19_00, // cents (EUR)
            'price_fallback_yearly' => 190_00,
            'interval' => 'month',
            'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 0),
            'description' => 'For independent makers and small product studies.',
            'entitlements' => [
                'credits' => 40,
                'model_drawing' => false,
                'formats' => ['pdf', 'svg'],
            ],
            'features' => [
                ['label' => '40 creation credits / month', 'included' => true],
                ['label' => 'PDF and SVG project briefs', 'included' => true],
                ['label' => 'Photo to Drawing workflow', 'included' => true],
                ['label' => '3D Model to Drawing processing', 'included' => false],
                ['label' => 'Basic specification drafts', 'included' => true],
                ['label' => 'Team review tools', 'included' => false],
            ],
        ],
        'studio' => [
            'name' => 'Studio',
            'subscription_name' => 'plans.studio',
            'price_id' => env('STRIPE_STUDIO_PRICE_ID'),
            'price_id_yearly' => env('STRIPE_STUDIO_PRICE_ID_YEARLY'),
            'price_fallback' => 89_00, // cents (EUR)
            'price_fallback_yearly' => 890_00,
            'interval' => 'month',
            'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 0),
            'popular' => true,
            'description' => 'For studios running multiple technical workflows.',
            'entitlements' => [
                'credits' => 250,
                'model_drawing' => true,
                'formats' => ['pdf', 'svg', 'dxf'],
            ],
            'features' => [
                ['label' => '250 creation credits / month', 'included' => true],
                ['label' => 'PDF, SVG and DXF workflows', 'included' => true],
                ['label' => 'Photo to Drawing workflow', 'included' => true],
                ['label' => '3D Model to Drawing processing', 'included' => true],
                ['label' => 'Advanced product specifications', 'included' => true],
                ['label' => 'Priority review and support', 'included' => true],
            ],
        ],
    ],

    // Minutes to cache live Stripe unit amounts per price id.
    'price_cache_ttl_minutes' => (int) env('STRIPE_PRICE_CACHE_TTL_MINUTES', 60),
];
