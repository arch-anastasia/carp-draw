<?php

namespace App\Console\Commands;

use App\Services\ErrorReporter;
use App\Services\StripePricingService;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * Answers the one question the billing page cannot: are the price ids in this
 * environment's .env real, live, and worth what the page says they are?
 *
 * The failure this exists for is silent by construction. StripePricingService
 * falls back to `price_fallback` whenever Stripe will not answer, so a
 * deployment whose STRIPE_*_PRICE_ID values belong to a different Stripe
 * account - or to test mode while the key is live - renders a perfectly
 * normal pricing page at the hard-coded price and then fails every single
 * checkout, because `newSubscription()->checkout()` sends that same unknown
 * price id and Stripe rejects it. The customer is told to try again in a
 * minute; retrying cannot work.
 *
 * That is not hypothetical. Production was observed serving every plan at its
 * exact `price_fallback` - including a yearly figure that has never existed as
 * a Stripe price for the id configured there - while /billing/subscribe
 * answered every press with "please try again in a minute". Nothing in the
 * application could say which of the two it was, which is what this command is
 * for. Run it anywhere the checkout is suspect; it needs nothing but the
 * environment it runs in.
 */
class BillingCheckCommand extends Command
{
    protected $signature = 'billing:check {--flush : Clear the cached prices first, so the check reads Stripe rather than the cache}';

    protected $description = 'Verify every configured Stripe price exists, is active, and matches its fallback';

    /**
     * Rows collected for the summary table.
     *
     * @var array<int, array<int, string>>
     */
    private array $rows = [];

    private bool $failed = false;

    public function handle(StripePricingService $pricing): int
    {
        $secret = (string) config('cashier.secret');

        if ($secret === '') {
            $this->error('STRIPE_SECRET is empty. Nothing can be charged and every price falls back to config/stripe.php.');

            return self::FAILURE;
        }

        $this->line('Stripe key: '.$this->describeKey($secret));

        if ($this->option('flush')) {
            $pricing->invalidate();
            $this->line('Cleared the cached prices.');
        }

        foreach ($this->configuredPrices() as [$label, $envVar, $priceId, $expected]) {
            $this->checkPrice($label, $envVar, $priceId, $expected);
        }

        if ($this->rows === []) {
            $this->warn('No price ids are configured at all.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['What', 'Env var', 'Price', 'Status', 'Detail'], $this->rows);

        if ($this->failed) {
            $this->newLine();
            $this->error('Checkout is broken in this environment. Fix the price ids above, then re-run with --flush.');

            /*
             * Reported, not merely printed, because the run that matters is
             * the scheduled one nobody is watching (routes/console.php). An
             * exit code in a cron log is how this broke unnoticed the first
             * time.
             */
            app(ErrorReporter::class)->reportIssue(
                'billing_config_broken',
                'Configured Stripe prices do not resolve; checkout cannot succeed in this environment.',
                ['subsystem' => 'billing', 'prices' => $this->brokenSummary()],
            );

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Every configured price resolves and matches its fallback.');

        return self::SUCCESS;
    }

    /**
     * Every price id this application can hand to Stripe, with the amount the
     * pricing page would advertise if Stripe stopped answering.
     *
     * Upsells and one-time products are usually empty (see config/stripe.php),
     * but they reach `checkout()` the same way plans do, so they are checked
     * the same way rather than only when someone remembers to.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: int|null}>
     */
    private function configuredPrices(): array
    {
        $out = [];

        foreach (config('stripe.plans', []) as $key => $plan) {
            if (! empty($plan['custom'])) {
                continue;
            }

            $upper = strtoupper($key);

            foreach ([
                ['monthly', 'price_id', 'price_fallback', "STRIPE_{$upper}_PRICE_ID"],
                ['yearly', 'price_id_yearly', 'price_fallback_yearly', "STRIPE_{$upper}_PRICE_ID_YEARLY"],
            ] as [$interval, $idKey, $fallbackKey, $envVar]) {
                $priceId = $plan[$idKey] ?? null;

                if (empty($priceId)) {
                    continue;
                }

                $out[] = ["plan {$key} ({$interval})", $envVar, $priceId, isset($plan[$fallbackKey]) ? (int) $plan[$fallbackKey] : null];
            }
        }

        foreach (config('stripe.upsells', []) as $key => $upsell) {
            if (! empty($upsell['price_id'])) {
                $out[] = ["upsell {$key}", '-', $upsell['price_id'], isset($upsell['price']) ? (int) $upsell['price'] : null];
            }
        }

        foreach (config('stripe.products', []) as $key => $product) {
            if (! empty($product['price_id'])) {
                $out[] = ["product {$key}", '-', $product['price_id'], isset($product['price']) ? (int) $product['price'] : null];
            }
        }

        return $out;
    }

    private function checkPrice(string $label, string $envVar, string $priceId, ?int $expected): void
    {
        try {
            $price = $this->stripe()->prices->retrieve($priceId);
        } catch (InvalidRequestException) {
            /*
             * The signature of the bug this command was written for: Stripe
             * knows the id's shape but not the id, which happens when the
             * price belongs to another account or to the other mode of this
             * one. Say so, rather than repeating Stripe's terse "No such
             * price" at someone who is already staring at a valid-looking id.
             */
            $this->flagFailure($label, $envVar, $priceId, 'MISSING', 'Not found under this key - the price belongs to a different Stripe account or mode.');

            return;
        } catch (ApiErrorException $e) {
            $this->flagFailure($label, $envVar, $priceId, 'ERROR', $e->getMessage());

            return;
        }

        if ($price->active === false) {
            $this->flagFailure($label, $envVar, $priceId, 'ARCHIVED', 'Archived in Stripe. Checkout refuses an inactive price.');

            return;
        }

        $amount = (int) $price->unit_amount;

        if ($expected !== null && $amount !== $expected) {
            /*
             * Not fatal - checkout works - but the page is advertising one
             * number while Stripe charges another the moment the live fetch
             * blips and the fallback takes over. That is the drift
             * StripePricingService warns about at request time.
             */
            $this->rows[] = [$label, $envVar, $priceId, 'DRIFT', "Stripe charges {$amount}, config falls back to {$expected}."];

            return;
        }

        $this->rows[] = [$label, $envVar, $priceId, 'OK', "{$amount} ".strtoupper((string) $price->currency)];
    }

    private function flagFailure(string $label, string $envVar, string $priceId, string $status, string $detail): void
    {
        $this->failed = true;
        $this->rows[] = [$label, $envVar, $priceId, $status, $detail];
    }

    /**
     * The broken rows as one line, for the report. Price ids are public
     * identifiers, and without them the alert cannot say which env var to fix.
     */
    private function brokenSummary(): string
    {
        $broken = array_filter($this->rows, fn (array $row): bool => ! in_array($row[3], ['OK', 'DRIFT'], true));

        return implode('; ', array_map(fn (array $row): string => "{$row[0]} [{$row[2]}] {$row[3]}", $broken));
    }

    /**
     * Cashier builds a client per call and offers nowhere to intercept it, so
     * the container is the seam: bound, tests supply their own; unbound - which
     * is every real run - it is Cashier's client, configured exactly as the
     * checkout that is being diagnosed configures its own.
     */
    private function stripe(): StripeClient
    {
        return app()->bound(StripeClient::class) ? app(StripeClient::class) : Cashier::stripe();
    }

    /**
     * The mode, never the key. A secret in a terminal is a secret in a
     * scrollback, a CI log and a screenshot.
     */
    private function describeKey(string $secret): string
    {
        return match (true) {
            str_starts_with($secret, 'sk_live_'), str_starts_with($secret, 'rk_live_') => 'live mode',
            str_starts_with($secret, 'sk_test_'), str_starts_with($secret, 'rk_test_') => 'test mode',
            default => 'unrecognised key format',
        };
    }
}
