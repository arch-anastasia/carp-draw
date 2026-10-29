<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyContentApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Forced before anything can fail. Without it a validation error on
         * this route redirects instead of returning 422, and a missing record
         * renders the Inertia error page - both because Laravel decides by
         * asking the request whether it wants JSON, and an agent posting raw
         * JSON often forgets to say so.
         */
        $request->headers->set('Accept', 'application/json');

        $configured = trim((string) config('blog.api.token', ''));

        if ($configured === '') {
            return response()->json([
                'message' => 'The content API is not configured on this server.',
            ], 503);
        }

        $presented = trim((string) $request->bearerToken());

        if ($presented === '' || ! hash_equals($configured, $presented)) {
            return response()->json([
                'message' => 'Missing or invalid API token. Send it as: Authorization: Bearer <token>.',
            ], 401);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
