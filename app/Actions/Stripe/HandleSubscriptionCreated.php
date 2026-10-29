<?php

namespace App\Actions\Stripe;

use App\Models\User;
use App\Notifications\NewSubscriberNotification;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleSubscriptionCreated
{
    public function __construct(
        private AnalyticsService $analytics,
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
            Log::debug('Subscription created for unknown customer', [
                'stripe_customer_id' => $stripeCustomerId,
                'subscription_id' => $subscription['id'],
            ]);

            return;
        }

        $priceId = $subscription['items']['data'][0]['price']['id'] ?? null;
        $interval = $subscription['items']['data'][0]['price']['recurring']['interval'] ?? null;

        $this->analytics->track($user, 'subscription_started', [
            'plan' => $priceId,
            'interval' => $interval,
            'trial' => ($subscription['trial_end'] ?? null) !== null,
            'is_swap' => ($subscription['metadata']['swap'] ?? null) === 'true',
        ]);

        Log::info('Subscription created', [
            'user_id' => $user->id,
            'subscription_id' => $subscription['id'],
            'price_id' => $priceId,
        ]);

        $adminEmail = config('branding.admin_email');

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify(new NewSubscriberNotification($user, [
                    'plan' => $priceId,
                    'interval' => $interval,
                    'trial' => ($subscription['trial_end'] ?? null) !== null,
                ]));
        }
    }
}
