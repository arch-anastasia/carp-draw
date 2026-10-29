<?php

namespace App\Services;

/**
 * The one true address of a page.
 *
 * Every canonical, Open Graph URL and structured-data @id on the site is built
 * here rather than from url()->current(). url()->current() echoes whatever Host
 * (or forwarded host) the request arrived on, so a stray alias, a staging
 * domain or a spoofed header would mint a canonical endorsing itself - and the
 * structured data would then disagree with the <link rel="canonical"> beside
 * it, which is worse than either being wrong alone.
 *
 * Trailing slashes are normalised away to match the 301 in
 * App\Http\Middleware\RedirectTrailingSlash, so /hairstyles and /hairstyles/
 * resolve to one URL in the markup as well as over the wire.
 */
class CanonicalUrl
{
    /**
     * The canonical URL for a path, or for the current request when none is
     * given. Query strings are dropped: they are tracking, not identity.
     *
     * The root keeps its slash: the home page is served at "host/", the
     * WebSite schema node lives at "host/#website", and a canonical of bare
     * "host" would be the one URL on the site that disagrees with both.
     */
    public function for(?string $path = null): string
    {
        $path ??= request()->getPathInfo();
        $path = rtrim($path, '/');

        return $this->origin().($path === '' ? '/' : $path);
    }

    /**
     * The site origin, with no trailing slash.
     */
    public function origin(): string
    {
        return rtrim(config('app.url'), '/');
    }

    /**
     * A stable identifier for a sitewide structured-data node - the
     * organisation and the website, which every page references rather than
     * restates. See config/seo.php.
     */
    public function schemaId(string $node): string
    {
        return $this->origin().config('seo.schema_ids.'.$node);
    }

    /**
     * Re-root an app-generated URL onto the canonical origin.
     *
     * route() and url() build against the host the request arrived on, so a
     * byline link generated that way puts the request host inside the
     * structured data even while the canonical beside it says otherwise - and
     * a crawler reading both is told two different things.
     *
     * "App-generated" is decided by host: anything on the host this request
     * arrived on, or already on the canonical host, is ours and gets re-rooted.
     * A genuinely external link - a writer's own Instagram, a CDN - is left
     * exactly as it is.
     */
    public function pin(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // A relative path is ours by definition.
        if ($host === null || $host === false) {
            return $this->origin().'/'.ltrim($url, '/');
        }

        $ours = array_filter([
            parse_url($this->origin(), PHP_URL_HOST),
            request()->getHost(),
        ]);

        if (! in_array($host, $ours, true)) {
            return $url;
        }

        $parts = parse_url($url);

        return $this->origin().($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /**
     * The identifier for the current page's own WebPage node.
     */
    public function pageSchemaId(?string $path = null): string
    {
        return $this->for($path).config('seo.schema_ids.webpage');
    }
}
