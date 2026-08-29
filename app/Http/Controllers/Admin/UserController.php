<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->latest('created_at')
            ->paginate(25)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toDateString(),
                'is_admin' => (bool) $user->is_admin,
                'subscribed' => $user->subscriptions()->where('stripe_status', 'active')->exists(),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'stats' => [
                'total' => User::query()->count(),
                'subscribers' => User::query()
                    ->whereHas('subscriptions', fn ($query) => $query->where('stripe_status', 'active'))
                    ->count(),
                'weekly' => User::query()->where('created_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }
}
