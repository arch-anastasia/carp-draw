<?php

namespace App\DataTransferObjects;

class SubscriptionResult
{
    public function __construct(
        public readonly string $successUrl,
        public readonly ?string $checkoutUrl = null,
    ) {}

    public function requiresCheckout(): bool
    {
        return $this->checkoutUrl !== null;
    }
}
