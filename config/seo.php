<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Sharing Card
    |--------------------------------------------------------------------------
    |
    | The default Open Graph / Twitter image, and its real dimensions. The
    | size is declared in the markup rather than left for a scraper to
    | discover: without it the first share of a URL renders as a bare link
    | while the crawler fetches the file.
    |
    */
    'social_image' => [
        'path' => 'og-v2.png',
        'width' => 1536,
        'height' => 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Title Display Budget
    |--------------------------------------------------------------------------
    |
    | Roughly what a search result shows before it truncates. The site name
    | is appended to a page title only while the result still fits.
    |
    */
    'title_budget' => 60,

    /*
    |--------------------------------------------------------------------------
    | Structured Data Node Identity
    |--------------------------------------------------------------------------
    |
    | Stable @id fragments for the sitewide schema nodes, so a crawler
    | reading two pages resolves one publisher rather than two look-alikes.
    |
    */
    'schema_ids' => [
        'organization' => '/#organization',
        'website' => '/#website',
        'webpage' => '#webpage',
    ],
];
