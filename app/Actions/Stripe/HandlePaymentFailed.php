<?php

namespace App\Actions\Stripe;

use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailed
{
    public function __construct(
        private AnalyticsService $analytics,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): void
    {
        $invoice = $payload['data']['object'];
        $stripeCustomerId = $invoice['customer'];

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            Log::debug('Payment failed for unknown customer', [
                'stripe_customer_id' => $stripeCustomerId,
                'invoice_id' => $invoice['id'],
            ]);

            return;
        }

        Log::warning('Payment failed for user', [
            'user_id' => $user->id,
            'invoice_id' => $invoice['id'],
            'amount_due' => $invoice['amount_due'] ?? null,
        ]);

        $this->analytics->track($user, 'payment_failed', [
            'amount_due_cents' => $invoice['amount_due'] ?? 0,
            'currency' => strtolower((string) ($invoice['currency'] ?? 'eur')),
            'attempt_count' => $invoice['attempt_count'] ?? 0,
        ]);

        try {
            $user->notify(new PaymentFailedNotification(
                invoiceId: $invoice['id'],
                amountDue: $invoice['amount_due'] ?? 0
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed notification', [
                'user_id' => $user->id,
                'invoice_id' => $invoice['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
