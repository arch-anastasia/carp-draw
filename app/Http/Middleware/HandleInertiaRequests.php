<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\BrowserTelemetry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'sentry' => BrowserTelemetry::config(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => (bool) $user->is_admin,
                    'has_password' => (bool) $user->has_password,
                    'language' => $user->language ?? 'en',
                ] : null,
            ],
            // Balance is a ledger sum; lazy so guest pages never pay for it.
            'credits' => fn () => $user ? $user->creditBalance() : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                // The project the workflow page should start polling after a submission.
                'project' => fn () => $request->session()->get('project'),
            ],
            'branding' => [
                'name' => config('branding.name'),
                'tagline' => config('branding.tagline'),
                'supportEmail' => config('branding.support_email'),
                'supportPhone' => config('branding.support_phone'),
            ],
        ];
    }
}
