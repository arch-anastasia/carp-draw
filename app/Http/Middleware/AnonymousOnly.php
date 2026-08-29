<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnonymousOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return redirect(config('app.home', '/app'));
        }

        return $next($request);
    }
}
