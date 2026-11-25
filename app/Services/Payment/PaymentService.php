<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Enums\PaymentProviders;
use App\Services\Shared\ProviderRegistry;

/** Payment service root, the public API the codebase calls. */
final class PaymentService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function createCheckout(CheckoutRequest $request, ?PaymentProviders $using = null): CheckoutSession
    {
        return $this->gateway($using)->createCheckout($request);
    }

    public function findCheckout(string $id, ?PaymentProviders $using = null): CheckoutSession
    {
        return $this->gateway($using)->findCheckout($id);
    }

    private function gateway(?PaymentProviders $using): PaymentGateway
    {
        /** @var PaymentGateway */
        return $this->registry->resolve('payment', $using?->value);
    }
}
