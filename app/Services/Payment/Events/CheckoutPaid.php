<?php

namespace App\Services\Payment\Events;

use App\Services\Payment\Data\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;

/** A verified provider webhook reported a paid checkout. Delivery is at least once, so listeners credit once per session id. */
final class CheckoutPaid
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly CheckoutSession $session,
    ) {}
}
