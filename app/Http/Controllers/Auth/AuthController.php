<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckEmailRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use App\Notifications\SetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the unified sign-in page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/SignIn', [
            'email' => $request->query('email', ''),
            /*
             * Membership and order come from config; whether a listed
             * provider is actually offered comes from its credentials, so a
             * half-configured provider is absent rather than a button that
             * dead-ends on the OAuth redirect.
             */
            'socialProviders' => array_values(array_filter(
                config('signin.providers', []),
                fn (string $provider) => (bool) config("services.{$provider}.client_id"),
            )),
        ]);
    }

    /**
     * Check if an email has an account and whether it can take a password.
     * JSON, for the two-step sign-in flow.
     */
    public function checkEmail(CheckEmailRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->trashed()) {
            // Same {errors} envelope as validation failures, so the sign-in
            // page's error rendering shows the message instead of dropping it.
            return response()->json([
                'exists' => false,
                'errors' => ['email' => ['This account has been deleted.']],
            ], 422);
        }

        return response()->json([
            'exists' => $user !== null,
            'hasPassword' => $user !== null && $user->has_password,
        ]);
    }

    /**
     * Passwordless entry — the marketing auth modal and the sign-in page's
     * email-only path both land here. Always answers with "link sent":
     * a new address gets an account and a set-password link, an existing
     * passwordless account gets the same link again, and an account with a
     * password gets a one-time signed sign-in link. Never logs in directly.
     */
    public function email(CheckEmailRequest $request): JsonResponse
    {
        $email = $request->validated()['email'];

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->trashed()) {
            return response()->json([
                'errors' => ['email' => ['This account has been deleted.']],
            ], 422);
        }

        if (! $user) {
            $user = User::create([
                'name' => '',
                'email' => $email,
                // Random hash, not null, to keep DB integrity; has_password
                // says whether the person knows it.
                'password' => Hash::make(Str::random(32)),
                'has_password' => false,
            ]);

            event(new Registered($user));
        }

        if ($user->has_password) {
            $user->notify(new MagicLinkNotification($user->generateMagicLoginUrl()));
        } else {
            $user->notify(new SetPasswordNotification($user->generateSetPasswordUrl()));
        }

        return response()->json(['status' => 'sent']);
    }

    /**
     * Handle login with password.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('workspace'));
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Full page redirect: home is Blade, not Inertia.
        return Inertia::location('/');
    }
}
