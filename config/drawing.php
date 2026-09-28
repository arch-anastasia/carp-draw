<?php

/*
|--------------------------------------------------------------------------
| Drawing pipeline
|--------------------------------------------------------------------------
|
| Everything the workflow → project → generation pipeline reads: credit
| pricing, per-mode upload rules, the provider catalogue, and the prompt
| fragments that encode the CarpDraw drawing standard (distilled from
| design-reference/style_presets/white_technical_furniture).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Credits
    |--------------------------------------------------------------------------
    |
    | Balance is an append-only ledger (credit_transactions); these numbers
    | are the grant/spend amounts. `free` is granted once at account creation
    | and never refills; subscriber allowances live in each plan's
    | entitlements (config/stripe.php) and roll over monthly.
    |
    */
    'credits' => [
        'free' => (int) env('DRAWING_FREE_CREDITS', 12),

        // A subscriber whose plan key vanished from config still deserves an
        // allowance — this is the fallback ProjectService uses.
        'subscribed_allowance' => (int) env('DRAWING_SUBSCRIBED_CREDITS', 40),

        'actions' => [
            'drawing' => (int) env('DRAWING_CREDITS_DRAWING', 4),
            'model_drawing' => (int) env('DRAWING_CREDITS_MODEL', 4),
            'specification' => (int) env('DRAWING_CREDITS_SPECIFICATION', 4),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload rules per workflow mode
    |--------------------------------------------------------------------------
    |
    | Mirrors the client-side wizard exactly — the server re-validates what
    | the browser already told the visitor.
    |
    */
    'uploads' => [
        'drawing' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'],
            'max_mb' => 15,
        ],
        'model_drawing' => [
            'extensions' => ['skp', '3ds', 'obj', 'dwg'],
            'max_mb' => 50,
        ],
        'specification' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'skp', '3ds', 'obj', 'dwg', 'dxf'],
            'max_mb' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | `default` picks a driver from the catalogue below. A driver whose key
    | is missing resolves to the fake stand-in so the full pipeline runs in
    | local dev and CI without spending money. ProviderRegistry::isLive()
    | reads the catalogue `production` flags and refuses stand-ins in
    | production.
    |
    */
    'provider' => [
        'default' => env('DRAWING_PROVIDER', 'fake'),

        // Private disk generated outputs and sources are stored on.
        'disk' => env('DRAWING_DISK', 'local'),

        // Seconds the generation job may run. Invariant when queue is redis:
        // provider timeout < job timeout < worker --timeout < retry_after.
        'job_timeout' => (int) env('DRAWING_JOB_TIMEOUT', 75),

        // Longest edge an input image is downscaled to before sending.
        'max_input_edge' => (int) env('DRAWING_MAX_INPUT_EDGE', 1024),

        'drivers' => [
            'openrouter' => [
                'production' => true,
                'requires_key' => true,
                'key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_IMAGE_MODEL', 'google/gemini-3-pro-image-preview'),
                'timeout' => (int) env('OPENROUTER_TIMEOUT', 60),
            ],
            'fake' => [
                'production' => false,
                'requires_key' => false,
            ],
            'gemini' => [
                'production' => true,
                'requires_key' => true,
                'key' => env('GEMINI_API_KEY'),
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
                'model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
                'describe_model' => env('GEMINI_DESCRIBE_MODEL', 'gemini-3.7-flash'),
                'timeout' => (int) env('GEMINI_TIMEOUT', 60),
            ],
            'openai' => [
                'production' => true,
                'requires_key' => true,
                'key' => env('OPENAI_API_KEY'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
                'describe_model' => env('OPENAI_DESCRIBE_MODEL', 'gpt-4o-mini'),
                'size' => env('OPENAI_IMAGE_SIZE', '1536x1024'),
                'quality' => env('OPENAI_IMAGE_QUALITY', 'high'),
                // Must stay under provider timeout < job_timeout (75) —
                // a provider allowed to outlive its job means killed jobs
                // and up to three billed API calls per generation.
                'timeout' => (int) env('OPENAI_TIMEOUT', 60),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Drawing standard prompts
    |--------------------------------------------------------------------------
    |
    | The White Technical Furniture standard as prompt language. PromptBuilder
    | owns sentence order; these fragments own the visual rules.
    |
    */
    'prompts' => [
        'style' => 'Pure white background. Solid black visible edges with crisp, uniform line weight. '
            .'Hidden edges as light grey dashed lines. No shading, no shadows, no colour fills, no perspective distortion. '
            .'Thin grey dimension lines with small arrowheads and dark grey dimension text, placed above and to the left of the object, '
            .'detail dimensions near the object and overall dimensions farther out, axis-aligned, never duplicated.',

        'standards' => [
            'furniture four views' => 'Create a CONCEPT drawing sheet from the supplied furniture photo. '
                .'Use exactly four views: Front upper left, Left side upper right, Top lower left, Isometric lower right. '
                .'The three orthographic views must share one scale and aligned proportions. '
                .'Isometric is a smaller overview. Keep all dimension labels outside the silhouette and readable. '
                .'Use only the supplied overall measurements. Never invent internal dimensions, material thicknesses, '
                .'hidden joints or hardware. Do not claim photogrammetry, corrected perspective or pixel measurements '
                .'without calculations. Unknown dimensions remain unlabelled. Preserve the visible panel and drawer layout. '
                .'Remove room objects and accessories without changing the furniture design. '
                .'Use white surfaces, thin black visible outlines and light grey dashed hidden edges. '
                .'Do not invent a logo or fill unknown title-block fields. Label the sheet CONCEPT — NOT FOR MANUFACTURE. '
                .'Return a single image sheet, not a claim of an editable 3D model.',
            'clean orthographic set' => 'Produce a clean orthographic drawing set: front view, side view and top (plan) view arranged on one sheet.',
            'fabrication sheet' => 'Produce a fabrication sheet: orthographic views with every dimension a workshop needs to build the piece, including material thicknesses.',
            'assembly overview' => 'Produce an assembly overview: an exploded or separated orthographic layout showing how the parts relate, with reference callouts.',
        ],

        'view_sets' => [
            'front side and plan views' => 'front view, side view and top (plan) view',
            'orthographic and isometric views' => 'orthographic front, side and plan views plus one isometric projection',
            'assembly and detail views' => 'assembly view plus enlarged detail views of joints and hardware',
        ],
    ],
];
