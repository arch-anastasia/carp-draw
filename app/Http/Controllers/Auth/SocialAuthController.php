<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ErrorReporter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialAuthController extends Controller
{
    /**
     * Providers we accept. Anything else on the URL is a 404, not an error
     * page — the route segment is user input. Read from the same config the
     * sign-in surfaces render from, so a provider can never be offered by one
     * and rejected by the other.
     *
     * @return array<int, string>
     */
    private function providers(): array
    {
        return config('signin.providers', []);
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless(in_array($provider, $this->providers(), true), 404);
        abort_unless($this->configured($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Find-or-create keyed on (provider, id) first, then on the verified
     * email the provider returns, so an existing passwordless account picks
     * up its social identity instead of duplicating.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, $this->providers(), true), 404);
        abort_unless($this->configured($provider), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            /*
             * The visitor gets a soft message and a way back in, but this is
             * never nothing: the same catch covers a visitor who hit "cancel"
             * on the consent screen and an OAuth app whose credentials have
             * expired — and the second one locks every Google user out until
             * somebody notices.
             */
            app(ErrorReporter::class)->report($e, 'social_auth_failed', [
                'subsystem' => 'auth',
                'provider' => $provider,
            ]);

            return redirect()->route('signin')
                ->with('error', 'That sign-in attempt did not complete. Try again, or use your email.');
        }

        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()->route('signin')
                ->with('error', ucfirst($provider).' did not share an email address for your account. Sign in with your email instead.');
        }

        $user = User::query()
            ->where('social_provider', $provider)
            ->where('social_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::withTrashed()->where('email', $email)->first();

            if ($user?->trashed()) {
                return redirect()->route('signin')->with('error', 'This account has been deleted.');
            }
        }

        if ($user) {
            $user->forceFill([
                'social_provider' => $provider,
                'social_id' => $socialUser->getId(),
                /*
                 * The provider only hands back an address it owns the sign-in
                 * for, so a social login is proof of the mailbox.
                 */
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $socialUser->getName() ?? '',
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'has_password' => false,
                'social_provider' => $provider,
                'social_id' => $socialUser->getId(),
            ]);

            $user->markEmailAsVerified();

            event(new Registered($user));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('workspace'));
    }

    private function configured(string $provider): bool
    {
        return (bool) config("services.{$provider}.client_id");
    }
}
