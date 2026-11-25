<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Shared\Exceptions\ProviderRejected;
use Illuminate\Support\Str;

/** In-memory gateway for tests and offline dev. A checkout is paid the moment it is created, and its url is the success url. */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, CheckoutSession> */
    private static array $sessions = [];

    /** @var array<string, string> session id by idempotency key */
    private static array $keys = [];

    public static function flush(): void
    {
        self::$sessions = [];
        self::$keys = [];
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        if (isset(self::$keys[$request->idempotencyKey])) {
            return $this->findCheckout(self::$keys[$request->idempotencyKey]);
        }

        $id = 'cs_fake_'.Str::random(24);

        self::$keys[$request->idempotencyKey] = $id;

        return self::$sessions[$id] = new CheckoutSession(
            id: $id,
            url: str_replace('{CHECKOUT_SESSION_ID}', $id, $request->successUrl),
            paid: true,
            amountCents: $request->amountCents,
            currency: $request->currency,
            paymentRef: 'pi_fake_'.Str::random(24),
            metadata: $request->metadata,
        );
    }

    public function findCheckout(string $id): CheckoutSession
    {
        return self::$sessions[$id] ?? throw new ProviderRejected('Fake payment: no such checkout.', 404, 'resource_missing');
    }
}
