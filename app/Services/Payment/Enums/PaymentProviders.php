<?php

namespace App\Services\Payment\Enums;

enum PaymentProviders: string
{
    case Fake = 'fake';
    case Stripe = 'stripe';
}
