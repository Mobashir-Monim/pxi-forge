<?php

namespace App\Services\Payment\Data;

/** A hosted checkout as the provider last reported it. */
final class CheckoutSession
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $id,
        // Where to send the buyer; null once the session is complete or expired.
        public readonly ?string $url,
        public readonly bool $paid,
        public readonly int $amountCents,
        public readonly string $currency,
        // The provider's payment reference (Stripe payment intent), set once paid.
        public readonly ?string $paymentRef,
        public readonly array $metadata,
    ) {}
}
