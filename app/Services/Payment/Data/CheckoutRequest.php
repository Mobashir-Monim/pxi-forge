<?php

namespace App\Services\Payment\Data;

/** A one-off hosted checkout for a fixed amount. */
final class CheckoutRequest
{
    /**
     * @param  string  $successUrl  may hold {CHECKOUT_SESSION_ID}, which the provider fills in
     * @param  array<string, string>  $metadata  echoed back on the session and its webhook
     */
    public function __construct(
        public readonly int $amountCents,
        public readonly string $description,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly string $idempotencyKey,
        public readonly ?string $customerEmail = null,
        public readonly array $metadata = [],
        public readonly string $currency = 'usd',
    ) {}
}
