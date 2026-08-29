<?php

use App\Services\CanonicalUrl;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

Route::get('/sitemap.xml', function () {
    $sitemap = Sitemap::create();

    /*
     * Priority and change frequency by route name. Only the public Blade
     * surface is listed — the workspace lives behind auth and is noindexed,
     * so publishing its URLs would only hand crawlers redirects.
     */
    $priorities = [
        'marketing.home' => [1.0, 'weekly'],
        'marketing.contact' => [0.8, 'monthly'],
        'marketing.privacy' => [0.3, 'yearly'],
    ];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName() ?? '';

        if (! str_starts_with($name, 'marketing.') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        if (str_contains($route->uri(), '{')) {
            continue;
        }

        [$priority, $frequency] = $priorities[$name] ?? [0.5, 'monthly'];

        // Through CanonicalUrl so every <loc> matches the page's own
        // canonical exactly (including the root's trailing slash).
        $sitemap->add(
            Url::create(app(CanonicalUrl::class)->for(
                $route->uri() === '/' ? '/' : '/'.$route->uri()
            ))
                ->setPriority($priority)
                ->setChangeFrequency($frequency)
        );
    }

    return $sitemap->toResponse(request());
})->name('seo.sitemap');

Route::get('/robots.txt', function () {
    /*
     * AI answer engines are a real acquisition channel for "how do I turn a
     * photo into a technical drawing" queries, so they are allowed
     * explicitly rather than left to the wildcard.
     */
    $aiCrawlers = ['GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'PerplexityBot', 'ClaudeBot', 'Google-Extended'];

    $lines = [
        'User-agent: *',
        'Allow: /',
        '',
        'Disallow: /app',
        'Disallow: /billing',
        'Disallow: /admin',
        'Disallow: /signin',
        'Disallow: /signup',
        'Disallow: /signout',
        'Disallow: /auth',
        'Disallow: /forgot-password',
        'Disallow: /reset-password',
        'Disallow: /set-password',
        'Disallow: /magic-link',
        'Disallow: /stripe',
        'Disallow: /health',
        'Disallow: /up',
        '',
    ];

    foreach ($aiCrawlers as $crawler) {
        $lines[] = 'User-agent: '.$crawler;
        $lines[] = 'Allow: /';
        $lines[] = '';
    }

    $lines[] = 'Sitemap: '.config('app.url').'/sitemap.xml';

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
})->name('seo.robots');

Route::get('/llms.txt', function () {
    $name = config('branding.name');
    $origin = rtrim(config('app.url'), '/');

    $lines = [
        "# {$name}",
        '',
        '> '.config('branding.tagline').' CarpDraw AI turns product photos and 3D models into',
        '> technical drawing concepts and structured product specifications for furniture,',
        '> interior and product teams.',
        '',
        '## Pages',
        '',
        "- [Home]({$origin}/): what the workspace does and how the three workflows fit together",
        "- [Contact]({$origin}/contact): reach the team",
        "- [Privacy]({$origin}/privacy): how uploads and account data are handled",
        '',
    ];

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('seo.llms');
