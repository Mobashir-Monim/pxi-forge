<?php

namespace App\Domains\Billing;

use App\Domains\Billing\Listeners\CreditPaidCheckout;
use App\Services\Payment\Events\CheckoutPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CheckoutPaid::class, CreditPaidCheckout::class);
    }
}
