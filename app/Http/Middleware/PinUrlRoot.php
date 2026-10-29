<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every generated URL is built against the configured host, not the one the
 * request arrived on.
 *
 * Laravel resolves url() and route() from the incoming Host header, so a
 * request reaching the app on an alias, a staging domain, a bare IP or a
 * spoofed forwarded host would render a page whose every link, form action and
 * absolute asset URL kept the visitor - or the crawler - on that host.
 *
 * The canonical tag already told search engines which URL was real. This makes
 * the rest of the page agree with it.
 *
 * Note this makes APP_URL load-bearing: it is the origin of every link the
 * application emits, so an environment with the wrong value produces a site
 * that links entirely to somewhere else.
 */
class PinUrlRoot
{
    public function handle(Request $request, Closure $next): Response
    {
        $root = config('app.url');

        if (is_string($root) && $root !== '') {
            URL::forceRootUrl($root);

            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        return $next($request);
    }
}
