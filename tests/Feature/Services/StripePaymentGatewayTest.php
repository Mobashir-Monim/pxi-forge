<?php

use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Enums\PaymentProviders;
use App\Services\Payment\Events\CheckoutPaid;
use App\Services\Payment\PaymentService;
use App\Services\Shared\Exceptions\ProviderRejected;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);
});

function checkoutRequest(): CheckoutRequest
{
    return new CheckoutRequest(1000, '$10 of credits', 'https://app/ok?s={CHECKOUT_SESSION_ID}', 'https://app/cancel', 'purchase-1', 'a@b.c', ['purchase_id' => '7']);
}

/** @return array<string, mixed> */
function sessionJson(array $overrides = []): array
{
    return ['id' => 'cs_test_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'payment_status' => 'unpaid',
        'amount_total' => 1000, 'currency' => 'usd', 'payment_intent' => null, 'metadata' => ['purchase_id' => '7'], ...$overrides];
}

function stripeSigned(string $body, ?int $at = null, string $secret = 'whsec_test'): array
{
    $at ??= time();

    return ['Stripe-Signature' => "t={$at},v1=bad,v1=".hash_hmac('sha256', "{$at}.{$body}", $secret), 'Content-Type' => 'application/json'];
}

it('opens a checkout session with the idempotency key', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(sessionJson())]);

    $session = app(PaymentService::class)->createCheckout(checkoutRequest(), PaymentProviders::Stripe);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Idempotency-Key', 'purchase-1')
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_x:'))
        && $r['mode'] === 'payment'
        && $r['line_items'][0]['price_data']['unit_amount'] === 1000
        && $r['metadata'] === ['purchase_id' => '7']);

    expect($session->url)->toBe('https://checkout.stripe.com/c/pay/cs_test_1')->and($session->paid)->toBeFalse();
});

it('maps a declined request to a rejection', function () {
    Http::fake(['*' => Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'amount_too_small', 'message' => 'too small']], 400)]);

    app(PaymentService::class)->createCheckout(checkoutRequest(), PaymentProviders::Stripe);
})->throws(ProviderRejected::class, 'too small');

it('dispatches a paid checkout from a verified webhook', function () {
    Event::fake([CheckoutPaid::class]);
    $body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => sessionJson(['payment_status' => 'paid', 'payment_intent' => 'pi_1'])]]);

    $this->call('POST', '/webhooks/payment/stripe', server: $this->transformHeadersToServerVars(stripeSigned($body)), content: $body)->assertOk();

    Event::assertDispatched(CheckoutPaid::class, fn (CheckoutPaid $e) => $e->eventId === 'evt_1' && $e->session->paymentRef === 'pi_1' && $e->session->amountCents === 1000);
});

it('ignores an unpaid completion and refuses a bad signature', function () {
    Event::fake([CheckoutPaid::class]);
    $body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => sessionJson()]]);

    $this->call('POST', '/webhooks/payment/stripe', server: $this->transformHeadersToServerVars(stripeSigned($body)), content: $body)->assertOk();
    $this->call('POST', '/webhooks/payment/stripe', server: $this->transformHeadersToServerVars(stripeSigned($body, time() - 301)), content: $body)->assertBadRequest();
    $this->call('POST', '/webhooks/payment/stripe', server: $this->transformHeadersToServerVars(stripeSigned($body, secret: 'whsec_other')), content: $body)->assertBadRequest();

    Event::assertNotDispatched(CheckoutPaid::class);
});

it('pays a fake checkout at once and replays its idempotency key', function () {
    $session = app(PaymentService::class)->createCheckout(checkoutRequest());

    expect($session->paid)->toBeTrue()
        ->and($session->url)->toBe("https://app/ok?s={$session->id}")
        ->and(app(PaymentService::class)->createCheckout(checkoutRequest())->id)->toBe($session->id);
});
