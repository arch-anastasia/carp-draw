<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResetPasswordController extends Controller
{
    /**
     * Show the reset password page — after validating the token, so a stale
     * link fails here with a way forward rather than on submit.
     */
    public function create(Request $request, string $token): Response|RedirectResponse
    {
        $email = $request->query('email', '');

        $user = User::where('email', $email)->first();

        if (! $user || ! Password::tokenExists($user, $token)) {
            return redirect()->route('password.request')
                ->with('error', 'That password reset link is invalid or has expired. Request a new one below.');
        }

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Handle the reset password request.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'has_password' => true,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Auth::login($user, remember: true);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('workspace')
                ->with('success', 'Your password has been reset successfully.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
