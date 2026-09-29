<?php

namespace Tests\Feature\Console;

use App\Services\AnalyticsService;
use App\Services\ErrorReporter;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Service\PriceService;
use Stripe\StripeClient;
use Tests\Support\RecordingErrorReporter;
use Tests\TestCase;

/**
 * The command exists to catch a deployment whose price ids do not belong to
 * its Stripe key. These are the states it has to tell apart.
 */
class BillingCheckCommandTest extends TestCase
{
    private RecordingErrorReporter $reporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporter = new RecordingErrorReporter(app(AnalyticsService::class));
        $this->app->instance(ErrorReporter::class, $this->reporter);

        config(['cashier.secret' => 'sk_test_fake']);
        config([
            'stripe.plans' => [
                'studio' => $this->plan('price_monthly', 'price_yearly'),
            ],
            'stripe.upsells' => [],
            'stripe.products' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_fails_when_the_stripe_secret_is_missing(): void
    {
        config(['cashier.secret' => null]);

        $this->artisan('billing:check')
            ->expectsOutputToContain('STRIPE_SECRET is empty')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_passes_when_every_price_resolves_at_the_configured_amount(): void
    {
        $this->fakeStripe([
            'price_monthly' => Price::constructFrom(['id' => 'price_monthly', 'active' => true, 'unit_amount' => 9_00, 'currency' => 'usd']),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        $this->artisan('billing:check')
            ->expectsOutputToContain('Every configured price resolves')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_when_a_price_does_not_exist_under_this_key(): void
    {
        // The production bug: a valid-looking id from another account or mode.
        $this->fakeStripe([
            'price_monthly' => new InvalidRequestException('No such price: price_monthly'),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        $this->artisan('billing:check')
            ->expectsOutputToContain('different Stripe account or mode')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_a_price_is_archived(): void
    {
        $this->fakeStripe([
            'price_monthly' => Price::constructFrom(['id' => 'price_monthly', 'active' => false, 'unit_amount' => 9_00, 'currency' => 'usd']),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        $this->artisan('billing:check')
            ->expectsOutputToContain('Checkout is broken in this environment')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_reports_drift_between_stripe_and_the_configured_fallback(): void
    {
        $this->fakeStripe([
            'price_monthly' => Price::constructFrom(['id' => 'price_monthly', 'active' => true, 'unit_amount' => 14_00, 'currency' => 'usd']),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        // Drift is not fatal - checkout still works - but it must be visible.
        $this->artisan('billing:check')
            ->expectsOutputToContain('DRIFT')
            ->assertExitCode(0);
    }

    /**
     * The daily scheduled run is the one that matters, and nobody reads its
     * exit code - so a broken config has to leave the building.
     */
    #[Test]
    public function it_reports_a_broken_configuration_so_the_scheduled_run_can_alert(): void
    {
        $this->fakeStripe([
            'price_monthly' => new InvalidRequestException('No such price: price_monthly'),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        $this->artisan('billing:check')->assertExitCode(1);

        $call = $this->reporter->firstFor('billing_config_broken');

        $this->assertNotNull($call);
        $this->assertStringContainsString('price_monthly', $call['context']['prices']);
    }

    #[Test]
    public function a_healthy_configuration_reports_nothing(): void
    {
        $this->fakeStripe([
            'price_monthly' => Price::constructFrom(['id' => 'price_monthly', 'active' => true, 'unit_amount' => 9_00, 'currency' => 'usd']),
            'price_yearly' => Price::constructFrom(['id' => 'price_yearly', 'active' => true, 'unit_amount' => 60_00, 'currency' => 'usd']),
        ]);

        $this->artisan('billing:check')->assertExitCode(0);

        $this->assertSame([], $this->reporter->calls);
    }

    #[Test]
    public function it_skips_custom_talk_to_us_tiers(): void
    {
        config(['stripe.plans' => [
            'salon' => ['name' => 'Enterprise', 'subscription_name' => 'plans.salon', 'custom' => true],
        ]]);

        $this->artisan('billing:check')
            ->expectsOutputToContain('No price ids are configured')
            ->assertExitCode(1);
    }

    /**
     * @param  array<string, Price|\Throwable>  $answers
     */
    private function fakeStripe(array $answers): void
    {
        $prices = Mockery::mock(PriceService::class);

        foreach ($answers as $id => $answer) {
            $expectation = $prices->shouldReceive('retrieve')->with($id);

            $answer instanceof \Throwable
                ? $expectation->andThrow($answer)
                : $expectation->andReturn($answer);
        }

        // `$client->prices` is StripeClient::__get, which delegates here.
        $client = Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('prices')->andReturn($prices);

        $this->app->instance(StripeClient::class, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(string $monthly, string $yearly): array
    {
        return [
            'name' => 'Plus',
            'subscription_name' => 'plans.studio',
            'price_id' => $monthly,
            'price_id_yearly' => $yearly,
            'price_fallback' => 9_00,
            'price_fallback_yearly' => 60_00,
            'interval' => 'month',
            'trial_days' => 0,
            'features' => [],
        ];
    }
}
