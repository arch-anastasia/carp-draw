<?php

namespace App\Actions\Stripe;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionDeleted
{
    public function __construct(
        private AnalyticsService $analytics
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): void
    {
        $subscription = $payload['data']['object'];
        $stripeCustomerId = $subscription['customer'];

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            Log::debug('Subscription deleted for unknown customer', [
                'stripe_customer_id' => $stripeCustomerId,
                'subscription_id' => $subscription['id'],
            ]);

            return;
        }

        $this->analytics->track($user, 'subscription_cancelled', [
            'plan' => $subscription['items']['data'][0]['price']['id'] ?? null,
            'reason' => $subscription['cancellation_details']['reason'] ?? 'unknown',
            'is_swap' => ($subscription['metadata']['swap'] ?? null) === 'true',
        ]);

        Log::info('Subscription cancelled', [
            'user_id' => $user->id,
            'subscription_id' => $subscription['id'],
            'subscription_status' => $subscription['status'] ?? null,
        ]);
    }
}
