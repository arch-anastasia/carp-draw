<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
    ) {}

    /**
     * Show the set password form. The signed URL is also the sign-in for a
     * passwordless account, so the visitor is logged in here — otherwise the
     * "secure sign-in link" the modal promised would land on a locked page.
     */
    public function create(Request $request, User $user): Response|RedirectResponse
    {
        /*
         * The reuse guard comes BEFORE the sign-in: once a password exists,
         * the link must stop being a credential — otherwise a leaked or
         * forwarded email stays a full magic-login for its remaining 24h
         * validity even after the owner secured the account.
         */
        if ($user->has_password) {
            return Auth::check()
                ? redirect()->route('workspace')->with('info', 'Your password has already been set.')
                : redirect()->route('signin')->with('info', 'Your password has already been set — sign in with it below.');
        }

        if (! Auth::check()) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        return Inertia::render('Auth/SetPassword', [
            'user' => $user->only(['id', 'email']),
            'action' => url()->signedRoute('password.set.store', ['user' => $user->id], now()->addHour()),
        ]);
    }

    /**
     * Set the user's password.
     */
    public function store(SetPasswordRequest $request, User $user): RedirectResponse
    {
        if ($user->has_password) {
            return redirect()->route('workspace')
                ->with('info', 'Your password has already been set.');
        }

        $user->update([
            'password' => $request->validated()['password'],
            'has_password' => true,
        ]);

        $this->analytics->track($user, 'account_password_set');

        Auth::setUser($user->fresh());

        return redirect()->route('workspace')
            ->with('success', 'Your password has been set successfully.');
    }

    /**
     * Resend the set password notification.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->has_password) {
            return redirect()->route('workspace')
                ->with('info', 'You have already set your password.');
        }

        $user->notify(new SetPasswordNotification($user->generateSetPasswordUrl()));

        return redirect()->route('workspace')
            ->with('success', 'A new password setup link has been sent to your email.');
    }
}
