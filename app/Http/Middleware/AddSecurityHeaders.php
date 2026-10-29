<?php

namespace App\Http\Middleware;

use App\Services\PreviewAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*', 'admin/*') || ($request->is('blog', 'blog/*') && (app(PreviewAccess::class)->granted($request) || $request->query('v') === 'draft'))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $this->buildCsp());

        return $response;
    }

    /**
     * Build the Content-Security-Policy header.
     *
     * Production baseline is `script-src 'self'` — no inline scripts anywhere
     * on the Blade marketing surface. Local development relaxes for Vite HMR,
     * and enabled integrations (PostHog, browser Sentry) add their own hosts
     * so nothing else has to know the policy exists.
     */
    private function buildCsp(): string
    {
        $isLocal = app()->environment('local');

        $scriptSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        $imgSrc = ["'self'", 'data:', 'blob:'];
        $imgSrc[] = 'https://i.ytimg.com';
        $editorialUrl = config('filesystems.disks.'.config('filesystems.runtime_images').'.url');
        if ($editorialUrl && parse_url($editorialUrl, PHP_URL_HOST)) {
            $imgSrc[] = parse_url($editorialUrl, PHP_URL_SCHEME).'://'.parse_url($editorialUrl, PHP_URL_HOST);
        }
        $fontSrc = ["'self'", 'data:'];
        $connectSrc = ["'self'"];
        $mediaSrc = ["'self'"];
        $workerSrc = ["'self'", 'blob:'];

        /*
         * Generated drawings are served from the drawing disk's presigned URL
         * when it is an object store (R2/S3), never proxied through our own
         * origin. Without that host in img-src the browser blocks every <img>
         * pointed at it.
         */
        $drawingDiskEndpoint = (string) config('filesystems.disks.'.config('drawing.provider.disk').'.endpoint');

        if ($drawingDiskEndpoint !== '') {
            $drawingHost = parse_url($drawingDiskEndpoint, PHP_URL_HOST);

            if (is_string($drawingHost) && $drawingHost !== '') {
                $imgSrc[] = "https://{$drawingHost}";
                // S3-style clients presign virtual-hosted URLs
                // (bucket.host), so one subdomain level is allowed too.
                $imgSrc[] = "https://*.{$drawingHost}";
            }
        }

        if ($isLocal) {
            $viteHost = 'http://localhost:5173';
            $scriptSrc[] = "'unsafe-inline'";
            $scriptSrc[] = $viteHost;
            $styleSrc[] = $viteHost;
            $fontSrc[] = $viteHost;
            $connectSrc[] = $viteHost;
            $connectSrc[] = 'ws://localhost:5173';
        }

        $browserDsn = (string) config('sentry.browser.dsn');
        if ($browserDsn !== '' && parse_url($browserDsn, PHP_URL_SCHEME) === 'https') {
            $host = parse_url($browserDsn, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $connectSrc[] = 'https://'.$host;
            }
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', array_unique($scriptSrc)),
            'style-src '.implode(' ', array_unique($styleSrc)),
            'img-src '.implode(' ', array_unique($imgSrc)),
            'font-src '.implode(' ', array_unique($fontSrc)),
            'connect-src '.implode(' ', array_unique($connectSrc)),
            'media-src '.implode(' ', array_unique($mediaSrc)),
            'worker-src '.implode(' ', array_unique($workerSrc)),
            'frame-src https://www.youtube-nocookie.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}
