<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AnonymousOnly;
use App\Http\Middleware\CollapseHtmlWhitespace;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\PinUrlRoot;
use App\Http\Middleware\RedirectTrailingSlash;
use App\Http\Middleware\ResetSeoState;
use App\Http\Middleware\VerifyContentApiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::group([], base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/blog.php'));
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            Route::middleware('web')
                ->group(base_path('routes/seo.php'));

            // Health check - no middleware (no session, CSRF, Inertia).
            // Used by container healthchecks and external uptime monitors.
            Route::get('/health', HealthController::class)
                ->name('health');

            // Marketing last so explicit paths above are claimed first.
            Route::middleware('web')
                ->group(base_path('routes/marketing.php'));
        },
    )
    /*
     * Listener auto-discovery is off: every listener in app/Listeners is
     * registered explicitly in AppServiceProvider::boot(). With discovery on
     * each one would be attached twice and every Stripe webhook processed
     * twice.
     */
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy (Docker network).
        $middleware->trustProxies(at: '*');

        // Before the session/CSRF stack: a trailing-slash 301 needs none of
        // it. PinUrlRoot goes first so even that redirect is built on the
        // configured host rather than the one the request arrived on.
        $middleware->web(prepend: [
            PinUrlRoot::class,
            ResetSeoState::class,
            RedirectTrailingSlash::class,
        ]);

        $middleware->web(append: [
            CollapseHtmlWhitespace::class,
            HandleInertiaRequests::class,
            AddSecurityHeaders::class,
        ]);

        $middleware->redirectGuestsTo('/signin');
        $middleware->redirectUsersTo('/app');

        /*
         * Redis-backed throttling except under phpunit: Redis outlives the
         * process, so throttle counters would accumulate across test runs.
         */
        if (env('APP_ENV') !== 'testing' && env('CACHE_STORE') === 'redis') {
            $middleware->throttleWithRedis();
        }

        /*
         * Stripe webhooks are server-to-server and carry no session. Apple's
         * OAuth callback is a cross-site form POST (response_mode=form_post)
         * that cannot carry this session's CSRF token; Socialite validates
         * the state nonce inside Apple's signed id_token instead.
         */
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'auth/apple/callback',
        ]);

        $middleware->alias([
            'is_admin' => IsAdmin::class,
            'content.api' => VerifyContentApiToken::class,
            'anonymous' => AnonymousOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Everything unhandled goes to Sentry from here. Must stay FIRST in
         * this closure: respond() below rewrites 500s into a friendly page,
         * and a handler registered after that rewrite sees the friendly page
         * rather than the crash behind it.
         */
        Integration::handles($exceptions);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());

        $exceptions->dontFlash(['file', 'password', 'password_confirmation', 'current_password']);

        // Expired signed set-password links render a friendly page instead of a 403.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->routeIs('password.set')) {
                $user = $request->route('user');

                return Inertia::render('Auth/SetPasswordExpired', [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ])->toResponse($request)->setStatusCode(403);
            }

            return null;
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if ($request->is('api/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

                return $response;
            }

            /*
             * 500/503/403 render the Inertia error page outside local/testing.
             * 404 is left to the framework so resources/views/errors/404.blade.php
             * (the ported not-found design) serves both surfaces.
             */
            if (! app()->environment(['local', 'testing']) && in_array($status, [500, 503, 403])) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            /*
             * Browser navigations get a friendly redirect on session expiry.
             * JSON callers (the wizard, the marketing fetch forms) must keep
             * the 419 — rewriting it to a redirect made fetch() follow it to
             * a 200 and report false success.
             */
            if ($status === 419 && ! $request->expectsJson()) {
                return back()->with('error', 'The page expired, please try again.');
            }

            return $response;
        });
    })->create();
