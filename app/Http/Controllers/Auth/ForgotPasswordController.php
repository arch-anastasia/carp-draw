<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForgotPasswordController extends Controller
{
    /**
     * Show the forgot password page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Handle the forgot password request.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // Silently succeeds when the email has no account — anti-enumeration.
        Password::sendResetLink($request->only('email'));

        // 'status' — the key the ForgotPassword page actually renders.
        return back()->with('status', 'If an account exists with that email, you will receive a password reset link.');
    }
}
