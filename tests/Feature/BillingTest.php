<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Drawing\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_page_shows_both_plans_at_fallback_prices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Plans')
                ->has('plans', 2)
                ->where('plans.0.key', 'maker')
                ->where('plans.0.name', 'Maker')
                ->where('plans.0.price', 1900)
                ->where('plans.0.currency', 'EUR')
                ->where('plans.1.key', 'studio')
                ->where('plans.1.price', 8900)
                ->where('plans.1.popular', true)
                ->where('subscription', null));
    }

    public function test_subscribers_see_their_current_plan(): void
    {
        $user = $this->createSubscribedUser();

        $this->actingAs($user)->get('/app/plans')
            ->assertInertia(fn ($page) => $page
                ->where('plans.1.current', true)
                ->where('subscription.plan', 'studio'));
    }

    public function test_subscribing_without_stripe_configured_fails_softly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/app/plans')->post('/billing/subscribe', [
            'plan' => 'maker',
            'interval' => 'monthly',
        ])
            ->assertRedirect('/app/plans')
            ->assertSessionHas('error');
    }

    public function test_unknown_plans_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/billing/subscribe', ['plan' => 'enterprise'])
            ->assertSessionHasErrors('plan');
    }

    public function test_subscriber_entitlements_raise_the_credit_allowance(): void
    {
        $user = $this->createSubscribedUser();

        // First balance read settles the monthly rollover at the plan's
        // allowance (Studio: 250).
        $this->actingAs($user)->get('/app/photo-to-drawing')
            ->assertInertia(fn ($page) => $page->where('credits', 250));
    }

    public function test_maker_plan_allowance_is_forty(): void
    {
        $user = $this->createSubscribedUser(type: 'plans.maker');

        $this->actingAs($user)->get('/app/photo-to-drawing')
            ->assertInertia(fn ($page) => $page
                ->where('credits', 40)
                ->where('canModelDrawing', false));
    }

    public function test_rechoosing_the_current_plan_is_a_noop_never_a_second_charge(): void
    {
        $user = $this->createSubscribedUser();

        $this->actingAs($user)->from('/app/plans')->post('/billing/subscribe', [
            'plan' => 'studio',
            'interval' => 'monthly',
        ])
            ->assertRedirect('/app/plans')
            ->assertSessionHas('info');

        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_choosing_the_other_plan_never_creates_a_second_subscription(): void
    {
        $user = $this->createSubscribedUser(type: 'plans.maker');

        // Stripe is unconfigured here, so the swap fails at the provider —
        // the load-bearing assertion is that no duplicate subscription row
        // ever appears and the failure is a calm flash, not a crash.
        $this->actingAs($user)->from('/app/plans')->post('/billing/subscribe', [
            'plan' => 'studio',
            'interval' => 'monthly',
        ])
            ->assertRedirect('/app/plans')
            ->assertSessionHas('error');

        $this->assertSame(1, $user->subscriptions()->count());
        $this->assertSame('plans.maker', $user->subscriptions()->first()->type);
    }

    public function test_first_subscription_window_tops_up_without_clawing_back_a_surplus(): void
    {
        $user = $this->createSubscribedUser(type: 'plans.maker');

        // Credits the account already holds above the new allowance (12 free
        // + 500 adjustment) must survive the opening top-up.
        app(CreditLedger::class)->adjust($user, 500, 'test surplus');

        $this->actingAs($user)->get('/app/photo-to-drawing')
            ->assertInertia(fn ($page) => $page->where('credits', 512));
    }
}
