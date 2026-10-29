<?php

/**
 * @see https://github.com/artesaos/seotools
 */

return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),
    'meta' => [
        /*
         * The default configurations to be used by the meta generator.
         */
        'defaults' => [
            // The SUFFIX appended to page titles (SEOMeta::setTitle's second
            // argument appends THIS, not the app name) — so it must be the
            // bare brand, or every page title drags a 44-char tagline.
            'title' => 'CarpDraw AI', // set false to total remove
            'titleBefore' => false,
            'description' => 'Create technical drawings from photos and 3D models, then prepare structured product specifications in one guided workspace.', // set false to total remove
            'separator' => ' | ',
            'keywords' => [],
            'canonical' => false, // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'robots' => false, // Set to 'all', 'none' or any combination of index/noindex and follow/nofollow
        ],
        /*
         * Webmaster tags are always added.
         */
        'webmaster_tags' => [
            'google' => env('GOOGLE_SITE_VERIFICATION'),
            'bing' => env('BING_SITE_VERIFICATION'),
            'alexa' => null,
            'pinterest' => null,
            'yandex' => null,
            'norton' => null,
        ],

        'add_notranslate_class' => false,
    ],
    'opengraph' => [
        /*
         * The default configurations to be used by the opengraph generator.
         */
        'defaults' => [
            'title' => 'CarpDraw AI — From reference to production-ready drawing', // set false to total remove
            'description' => 'A focused workspace for photo-to-drawing, 3D-to-drawing and product specification workflows.', // set false to total remove
            'url' => false, // Set null for using Url::current(), set false to total remove
            'type' => false,
            'site_name' => false,
            'images' => [],
        ],
    ],
    'twitter' => [
        /*
         * The default values to be used by the twitter cards generator.
         */
        'defaults' => [
            // 'card'        => 'summary',
            // 'site'        => '@LuizVinicius73',
        ],
    ],
    'json-ld' => [
        /*
         * The default configurations to be used by the json-ld generator.
         */
        'defaults' => [
            'title' => 'CarpDraw AI — From reference to production-ready drawing', // set false to total remove
            'description' => 'A focused workspace for photo-to-drawing, 3D-to-drawing and product specification workflows.', // set false to total remove
            'url' => false, // Set to null or 'full' to use Url::full(), set to 'current' to use Url::current(), set false to total remove
            'type' => 'WebPage',
            'images' => [],
        ],
    ],
];
