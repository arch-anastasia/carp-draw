<?php

return [
    'reserved_slugs' => ['authors'],
    'api' => ['token' => env('BLOG_API_TOKEN'), 'throttle' => (int) env('BLOG_API_THROTTLE', 60)],
    'indexnow' => ['key' => env('INDEXNOW_KEY'), 'enabled' => filled(env('INDEXNOW_KEY'))],
    'start_here' => [],
    'house_author' => [
        'name' => env('BLOG_HOUSE_AUTHOR_NAME', 'CarpDraw Editorial'),
        'username' => env('BLOG_HOUSE_AUTHOR_USERNAME', 'carpdraw'),
        'role' => 'Editorial team',
        'bio' => 'Guides to furniture planning, technical drawings, and product specifications.',
    ],
    'ending' => [
        'eyebrow' => 'Plan before you build',
        'text' => 'Turn a furniture photo into a drawing concept and structured specifications. Verify dimensions and construction details before manufacture.',
        'cta_text' => 'Explore CarpDraw',
        'cta_url' => '/',
    ],
];
