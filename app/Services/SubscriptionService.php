<?php

namespace App\Services;

use App\DataTransferObjects\SubscriptionResult;
use App\Models\User;
use App\Services\Drawing\CreditLedger;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscriptionService
{
    public function __construct(
        private StripePricingService $pricing,
        private CreditLedger $ledger,
    ) {}

    /**
     * The plans payload the plans page renders, in the contract shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPlansForUser(User $user): array
    {
        $currentKey = $this->getCurrentPlanKey($user);
        $plans = [];

        foreach (config('stripe.plans') as $key => $plan) {
            $plans[] = [
                'key' => $key,
                'name' => $plan['name'],
                'price' => $this->pricing->getPriceInCents($key),
                'currency' => strtoupper((string) config('cashier.currency', 'eur')),
                'interval' => $plan['interval'] ?? 'month',
                'description' => $plan['description'] ?? '',
                'features' => $plan['features'] ?? [],
                'popular' => (bool) ($plan['popular'] ?? false),
                'current' => $key === $currentKey,
            ];
        }

        return $plans;
    }

    /**
     * The current subscription's display state for the plans page, or null.
     *
     * @return array{plan: string, on_grace_period: bool, ends_at: string|null}|null
     */
    public function subscriptionState(User $user): ?array
    {
        $current = $this->getCurrentPlan($user);

        if (! $current) {
            return null;
        }

        return [
            'plan' => $current['key'],
            'on_grace_period' => $current['subscription']->onGracePeriod(),
            'ends_at' => $current['subscription']->ends_at?->toDateString(),
        ];
    }

    /**
     * Subscribe the user to a plan.
     *
     * Guarded against concurrent duplicates: a user with ANY active plan
     * must never reach newSubscription() here — re-choosing the same plan
     * is a no-op and choosing the other one is a swap. Without this, a
     * Maker subscriber pressing "Choose Studio" would be billed for both
     * plans every month. The controller routes those cases before calling
     * this, and the assertion below is the backstop.
     */
    public function subscribe(User $user, string $planKey, string $interval = 'monthly'): SubscriptionResult
    {
        $plan = config("stripe.plans.{$planKey}");

        if (! $plan) {
            throw new NotFoundHttpException('Plan not found');
        }

        if ($this->getCurrentPlan($user) !== null) {
            throw new \LogicException('subscribe() called for a user with an active plan — use swap().');
        }

        // A yearly ask quietly falls back to monthly until the yearly price
        // exists in Stripe, rather than 500ing on a null price id.
        $priceId = $interval === 'yearly' && ! empty($plan['price_id_yearly'])
            ? $plan['price_id_yearly']
            : $plan['price_id'];

        $trialDays = (int) ($plan['trial_days'] ?? 0);
        $eligibleForTrial = $trialDays > 0 && ! $this->hasEverSubscribedToAnyPlan($user);

        $successUrl = route('billing.success');

        $subscription = $user->newSubscription($plan['subscription_name'], $priceId);

        if ($eligibleForTrial) {
            $subscription->trialUntil(now()->addDays($trialDays)->endOfDay());
        }

        // Use the existing payment method when there is one — a returning
        // subscriber should not walk through Checkout again.
        $paymentMethod = $user->defaultPaymentMethod() ?? $user->paymentMethods()->first();

        if ($paymentMethod) {
            $subscription->create($paymentMethod->id);

            $this->settleCreditsForPlanStart($user, $planKey);

            return new SubscriptionResult($successUrl);
        }

        $checkout = $subscription->allowPromotionCodes()->checkout([
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.index'),
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        return new SubscriptionResult($successUrl, $checkout->asStripeCheckoutSession()->url);
    }

    /**
     * Swap between plans, tagged on Stripe so webhook analytics can tell a
     * swap from a churn.
     *
     * CREATE FIRST, cancel second: a card decline on the new plan must
     * leave the old subscription untouched — the failure mode of the
     * cancel-first order was a customer with nothing at all mid-period.
     */
    public function swap(User $user, string $toPlanKey): void
    {
        $current = $this->getCurrentPlan($user);

        if (! $current) {
            throw new NotFoundHttpException('No active subscription found');
        }

        $toPlan = config("stripe.plans.{$toPlanKey}");

        if (! $toPlan) {
            throw new NotFoundHttpException('Plan not found');
        }

        if ($current['key'] === $toPlanKey) {
            return;
        }

        $paymentMethod = $user->defaultPaymentMethod() ?? $user->paymentMethods()->first();

        if (! $paymentMethod) {
            throw new \RuntimeException('No payment method available');
        }

        // Tagged on Stripe BEFORE either webhook can fire, so the analytics
        // actions read is_swap straight off the payload with no race.
        $current['subscription']->updateStripeSubscription(['metadata' => ['swap' => 'true']]);

        try {
            $user->newSubscription($toPlan['subscription_name'], $toPlan['price_id'])
                ->withMetadata(['swap' => 'true'])
                ->create($paymentMethod->id);
        } catch (IncompletePayment $e) {
            /*
             * 3D Secure: the new subscription exists in `incomplete` and the
             * visitor is about to confirm it on Cashier's payment page. The
             * old plan is cancelled here so a confirmed payment cannot leave
             * two active subscriptions; an abandoned confirmation expires the
             * incomplete subscription on Stripe's side.
             */
            $current['subscription']->cancelNow();

            throw $e;
        }

        $current['subscription']->cancelNow();

        $this->settleCreditsForPlanChange($user, $current['key'], $toPlanKey);
    }

    /**
     * Credit side of a fresh subscription: open a window at the plan's full
     * allowance immediately — the credits the visitor just paid for arrive
     * today, not at the first rollover.
     */
    public function settleCreditsForPlanStart(User $user, string $planKey): void
    {
        $credits = (int) config("stripe.plans.{$planKey}.entitlements.credits", 0);

        if ($credits > 0) {
            $this->ledger->grantUpgradeAllowance($user, $credits);
        }
    }

    /**
     * Credit side of a plan change. An UPGRADE starts a fresh window at the
     * new allowance immediately; a DOWNGRADE leaves the balance untouched
     * until the next monthly rollover.
     */
    public function settleCreditsForPlanChange(User $user, string $fromPlanKey, string $toPlanKey): void
    {
        $fromCredits = (int) config("stripe.plans.{$fromPlanKey}.entitlements.credits", 0);
        $toCredits = (int) config("stripe.plans.{$toPlanKey}.entitlements.credits", 0);

        if ($toCredits > $fromCredits) {
            $this->ledger->grantUpgradeAllowance($user, $toCredits);
        }
    }

    /**
     * Find the user's current plan key and subscription.
     *
     * @return array{key: string, plan: array<string, mixed>, subscription: Subscription}|null
     */
    public function getCurrentPlan(User $user): ?array
    {
        foreach (config('stripe.plans') as $key => $plan) {
            $subscription = $user->subscription($plan['subscription_name']);

            if ($subscription && $subscription->active()) {
                return [
                    'key' => $key,
                    'plan' => $plan,
                    'subscription' => $subscription,
                ];
            }
        }

        return null;
    }

    public function getCurrentPlanKey(User $user): ?string
    {
        return $this->getCurrentPlan($user)['key'] ?? null;
    }

    public function hasMainSubscription(User $user): bool
    {
        return $this->getCurrentPlan($user) !== null;
    }

    /**
     * Whether the user has ever subscribed to any plan — trial-abuse guard
     * that survives plan switches.
     */
    public function hasEverSubscribedToAnyPlan(User $user): bool
    {
        foreach (config('stripe.plans') as $plan) {
            if ($user->hasEverSubscribedTo($plan['subscription_name'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cancel (grace period until the end of the paid period).
     */
    public function cancel(User $user): void
    {
        $current = $this->getCurrentPlan($user);

        if ($current !== null) {
            $current['subscription']->cancel();
        }
    }

    /**
     * Resume a subscription still on its grace period.
     */
    public function resume(User $user): bool
    {
        foreach (config('stripe.plans') as $plan) {
            $subscription = $user->subscription($plan['subscription_name']);

            if ($subscription?->onGracePeriod()) {
                $subscription->resume();

                return true;
            }
        }

        return false;
    }
}
