<?php

namespace App\Services\Payment\Providers;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\Data\CheckoutRequest;
use App\Services\Payment\Data\CheckoutSession;
use App\Services\Payment\Events\CheckoutPaid;
use App\Services\Shared\Contracts\HandlesWebhooks;
use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\Shared\Exceptions\ProviderRateLimited;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Checkout over its REST API (form-encoded), no SDK. Test-mode keys point it at the Stripe sandbox.
 * ponytail: runs on the account's default API version; the fields read here are stable across versions. Pin Stripe-Version if that changes.
 */
final class StripePaymentGateway implements HandlesWebhooks, PaymentGateway
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    private const SIGNATURE_TOLERANCE = 300;

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $form = array_filter([
            'mode' => 'payment',
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'customer_email' => $request->customerEmail,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $request->currency,
                    'unit_amount' => $request->amountCents,
                    'product_data' => ['name' => $request->description],
                ],
            ]],
            // Charged in the request's currency only. Adaptive Pricing would let the buyer pay in theirs, and the amount
            // paid would no longer match what's being bought.
            'adaptive_pricing' => ['enabled' => 'false'],
            'metadata' => $request->metadata,
            // On the payment intent too, so it reads right in the Stripe dashboard.
            'payment_intent_data' => ['metadata' => $request->metadata],
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return $this->session($this->send(
            fn (PendingRequest $http) => $http->withHeader('Idempotency-Key', $request->idempotencyKey)->post('checkout/sessions', $form),
        ));
    }

    public function findCheckout(string $id): CheckoutSession
    {
        return $this->session($this->send(fn (PendingRequest $http) => $http->get('checkout/sessions/'.rawurlencode($id))));
    }

    public function handleWebhook(Request $request): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            Log::error('Stripe webhook refused: STRIPE_WEBHOOK_SECRET is not set.');

            return response('invalid signature', Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getContent();

        if (! $this->signatureValid($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            return response('invalid signature', Response::HTTP_BAD_REQUEST);
        }

        /** @var array{id?: string, type?: string, data?: array{object?: array<string, mixed>}} $event */
        $event = json_decode($payload, true) ?: [];
        $type = $event['type'] ?? '';

        // A card payment is paid on completion; async methods report later through async_payment_succeeded.
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && isset($event['id'], $event['data']['object'])) {
            $session = $this->session($event['data']['object']);

            if ($session->paid) {
                CheckoutPaid::dispatch($event['id'], $session);
            }
        }

        return response('ok');
    }

    /** Stripe may send several v1 signatures while a secret rolls; any one matching is enough. */
    private function signatureValid(string $payload, string $header, string $secret): bool
    {
        $timestamp = 0;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

            match ($key) {
                't' => $timestamp = (int) $value,
                'v1' => $signatures[] = $value,
                default => null,
            };
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    private function session(array $data): CheckoutSession
    {
        /** @var array<string, string> $metadata */
        $metadata = $data['metadata'] ?? [];
        $intent = $data['payment_intent'] ?? null;

        return new CheckoutSession(
            id: (string) $data['id'],
            url: isset($data['url']) ? (string) $data['url'] : null,
            paid: ($data['payment_status'] ?? null) === 'paid',
            amountCents: (int) ($data['amount_total'] ?? 0),
            currency: (string) ($data['currency'] ?? 'usd'),
            paymentRef: is_string($intent) ? $intent : null,
            metadata: $metadata,
        );
    }

    /**
     * @param  callable(PendingRequest): ClientResponse  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $key = (string) config('services.stripe.secret');

        if ($key === '') {
            throw new ProviderNotConfigured('STRIPE_SECRET is not set.');
        }

        try {
            $response = $call(Http::baseUrl(self::BASE_URL)
                ->withBasicAuth($key, '')
                ->asForm()
                ->acceptJson()
                ->timeout(15));
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable("Stripe unreachable: {$e->getMessage()}", $e);
        }

        if ($response->successful()) {
            /** @var array<string, mixed> */
            return $response->json() ?? [];
        }

        $code = $response->json('error.code');
        $message = 'Stripe: '.($response->json('error.message') ?? "HTTP {$response->status()}");

        throw match (true) {
            $response->status() === 429 => new ProviderRateLimited($message, errorCode: $code),
            $response->serverError() => new ProviderUnavailable($message),
            default => new ProviderRejected($message, $response->status(), $code, $response->json('error.type')),
        };
    }
}
