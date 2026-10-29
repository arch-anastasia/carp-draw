<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Guest routes (accessible only when not logged in)
Route::middleware('guest')->group(function () {
    // Unified sign-in flow
    Route::get('/signin', [AuthController::class, 'show'])->name('signin');
    Route::post('/signin', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/signin/check-email', [AuthController::class, 'checkEmail'])
        ->name('signin.check-email');

    // Passwordless entry (marketing auth modal + email-only sign-in).
    // Throttled on top of the request's own rate limiting: it sends mail.
    Route::post('/signin/email', [AuthController::class, 'email'])
        ->middleware('throttle:10,1')
        ->name('signin.email');

    // Legacy paths people type by hand
    Route::get('/signup', [AuthController::class, 'show'])->name('register');
    Route::post('/signup', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', fn () => redirect()->route('signin'))->name('login');

    // OAuth sign-in (Google, Apple). The controller 404s unknown or
    // unconfigured providers.
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');

    /*
    | POST as well as GET: Apple sets response_mode=form_post, so it returns
    | the authorisation code — and, on first authorisation only, the user's
    | name — as a form body rather than query parameters. A GET-only route
    | answers that with 405 and the name is lost for good. Google still
    | arrives as a GET.
    */
    Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/signout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/set-password/resend', [SetPasswordController::class, 'resend'])->name('password.set.resend');
});

// Signed-link routes. The signature is the credential, so no auth/guest
// middleware: a signed sign-in link must work in a fresh browser.
Route::middleware('signed')->group(function () {
    Route::get('/set-password/{user}', [SetPasswordController::class, 'create'])->name('password.set');
    Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->name('password.set.store');
    Route::get('/magic-link/{user}', MagicLinkController::class)->name('magic.login');
});
