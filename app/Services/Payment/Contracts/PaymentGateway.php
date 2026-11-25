<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;

/** Taking one-off payments in app terms. */
interface PaymentGateway
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** Read a session back, so the return page can settle a payment without waiting on the webhook. */
    public function findCheckout(string $id): CheckoutSession;
}
