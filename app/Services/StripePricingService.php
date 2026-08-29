<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;

/**
 * Reads prices from Stripe (source of truth) with a local cache.
 *
 * - If STRIPE_SECRET is set and the price_id exists, we fetch from Stripe
 *   and cache for 1 hour (configurable via stripe.price_cache_ttl_minutes).
 * - Otherwise, we fall back to the hardcoded defaults in config/stripe.php
 *   so local dev and CI don't blow up when Stripe isn't configured.
 *
 * Prices are cached per price_id, so switching plan.price_id in config
 * automatically invalidates via the different cache key.
 */
class StripePricingService
{
    public function getPriceInCents(string $planKey, string $interval = 'monthly'): int
    {
        $plan = config("stripe.plans.{$planKey}");
        abort_unless($plan, 404, "Unknown plan: {$planKey}");

        $priceId = $interval === 'yearly' ? ($plan['price_id_yearly'] ?? null) : ($plan['price_id'] ?? null);
        $fallback = (int) ($interval === 'yearly'
            ? ($plan['price_fallback_yearly'] ?? 0)
            : ($plan['price_fallback'] ?? 0));

        if (empty($priceId) || empty(config('cashier.secret'))) {
            return $fallback;
        }

        return (int) Cache::remember(
            $this->cacheKey($priceId),
            now()->addMinutes((int) config('stripe.price_cache_ttl_minutes', 60)),
            function () use ($priceId, $fallback) {
                try {
                    $stripePrice = Cashier::stripe()->prices->retrieve($priceId);

                    return (int) $stripePrice->unit_amount;
                } catch (ApiErrorException $e) {
                    /*
                     * The page still renders, at the hard-coded fallback
                     * price. That is the danger: if the fallback and Stripe
                     * have drifted, we advertise one number and charge
                     * another, and the only sign of it was a warning in a log
                     * file. Reported, with both numbers, so the drift is
                     * visible before a customer finds it.
                     */
                    app(ErrorReporter::class)->warn(
                        'stripe_price_unavailable',
                        'Could not read a live price from Stripe; showing the fallback.',
                        [
                            'subsystem' => 'billing',
                            'price_id' => $priceId,
                            'fallback_cents' => $fallback,
                            'error' => $e->getMessage(),
                        ],
                    );

                    return $fallback;
                }
            }
        );
    }

    /**
     * Annotate every plan in config with its live price (or fallback).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getEnrichedPlans(): array
    {
        $out = [];

        foreach (config('stripe.plans', []) as $key => $plan) {
            $out[$key] = array_merge($plan, [
                'price' => $this->getPriceInCents($key),
            ]);
        }

        return $out;
    }

    public function invalidate(?string $priceId = null): void
    {
        if ($priceId) {
            Cache::forget($this->cacheKey($priceId));

            return;
        }

        foreach (config('stripe.plans', []) as $plan) {
            if (! empty($plan['price_id'])) {
                Cache::forget($this->cacheKey($plan['price_id']));
            }
        }
    }

    private function cacheKey(string $priceId): string
    {
        return "stripe:price:{$priceId}";
    }
}
