<?php

namespace App\Listeners;

use App\Actions\Stripe\HandlePaymentFailed;
use App\Actions\Stripe\HandleSubscriptionCreated;
use App\Actions\Stripe\HandleSubscriptionDeleted;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    /**
     * How long to remember a processed Stripe event id. Stripe retries failed
     * webhooks for up to ~3 days, so 7 days gives a comfortable margin.
     */
    private const PROCESSED_EVENT_TTL_DAYS = 7;

    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $eventId = $payload['id'] ?? null;

        // Stripe retries webhooks on non-2xx responses. Without dedup, every
        // retry re-fires our side effects (credit grants, analytics, admin
        // mail). Atomically claim the event id; if a handler then throws
        // (transient DB failure), RELEASE the claim so Stripe's retry can
        // legitimately re-process.
        if ($eventId !== null && ! $this->claimEvent($eventId)) {
            return;
        }

        try {
            $handler = match ($payload['type']) {
                'customer.subscription.created' => app(HandleSubscriptionCreated::class),
                'customer.subscription.deleted' => app(HandleSubscriptionDeleted::class),
                'invoice.payment_failed' => app(HandlePaymentFailed::class),
                default => null,
            };

            $handler?->__invoke($payload);
        } catch (\Throwable $e) {
            if ($eventId !== null) {
                $this->releaseEvent($eventId);
            }

            throw $e;
        }
    }

    private function claimEvent(string $eventId): bool
    {
        return Cache::add(
            'stripe:webhook:'.$eventId,
            true,
            now()->addDays(self::PROCESSED_EVENT_TTL_DAYS)
        );
    }

    private function releaseEvent(string $eventId): void
    {
        Cache::forget('stripe:webhook:'.$eventId);
    }
}
