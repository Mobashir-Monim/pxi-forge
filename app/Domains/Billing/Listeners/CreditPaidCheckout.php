<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Actions\Command\CompleteCreditPurchase;
use App\Services\Payment\Events\CheckoutPaid;

/**
 * Settles a purchase from Stripe's webhook, for users who close the tab before the return page loads. Runs inside the
 * webhook request, so if it throws, the non-2xx answer makes Stripe deliver it again. Redeliveries are harmless: a
 * purchase is credited once however often it's settled.
 */
final class CreditPaidCheckout
{
    public function handle(CheckoutPaid $event): void
    {
        CompleteCreditPurchase::run($event->session);
    }
}
