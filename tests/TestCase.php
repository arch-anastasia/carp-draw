<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Throttle counters must not leak 429s between tests.
        Cache::clear();
        Http::preventStrayRequests();
    }

    /**
     * A user with an active subscription row (no Stripe round-trip).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createSubscribedUser(array $attributes = [], string $type = 'plans.studio'): User
    {
        $user = User::factory()->create($attributes);

        $this->addSubscription($user, $type);

        return $user;
    }

    /**
     * Insert a Cashier subscription row directly, the way a webhook would
     * leave it.
     */
    protected function addSubscription(User $user, string $type = 'plans.studio'): void
    {
        $user->subscriptions()->create([
            'type' => $type,
            'stripe_id' => 'sub_'.Str::random(14),
            'stripe_status' => 'active',
            'stripe_price' => 'price_'.Str::random(14),
            'quantity' => 1,
        ]);
    }
}
