<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MagicLinkController extends Controller
{
    /**
     * Sign in through a signed one-time link. The `signed` middleware has
     * already vouched for the URL; the cache claim below is what makes
     * "one-time" true — a signature cannot be revoked, so its first use is
     * recorded for the rest of its validity window and every replay is
     * turned away.
     */
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $claim = 'magic-link:'.hash('sha256', (string) $request->query('signature'));

        if (! Cache::add($claim, true, now()->addHours(2))) {
            return redirect()->route('signin')
                ->with('error', 'That sign-in link has already been used. Enter your email for a fresh one.');
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->route('workspace');
    }
}
