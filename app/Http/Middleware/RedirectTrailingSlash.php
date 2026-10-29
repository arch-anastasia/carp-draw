<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTrailingSlash
{
    /**
     * 301 any GET/HEAD request whose path carries a trailing slash to the
     * canonical slash-less URL. Without this, /hairstyles and /hairstyles/
     * both answer 200 and split crawl budget across duplicate URLs.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (in_array($request->method(), ['GET', 'HEAD'], true) && $path !== '/' && str_ends_with($path, '/')) {
            $query = $request->getQueryString();

            return redirect()->to(rtrim($path, '/').($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
