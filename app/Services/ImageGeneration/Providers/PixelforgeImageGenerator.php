<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\Contracts\ImageGenerator;
use App\Services\ImageGeneration\Data\Generation;
use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Data\Pricing;
use App\Services\ImageGeneration\Data\UsageDay;
use App\Services\ImageGeneration\Events\GenerationUpdated;
use App\Services\Shared\Contracts\HandlesWebhooks;
use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\Shared\Exceptions\ProviderRateLimited;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pixelforge over HTTP. One attempt per call: retry and backoff belong to the queued job that calls this, which reads retryAfter.
 */
final class PixelforgeImageGenerator implements HandlesWebhooks, ImageGenerator
{
    /** Signatures older than this are replays. */
    private const SIGNATURE_TOLERANCE = 300;

    public function create(GenerationRequest $request, string $idempotencyKey): Generation
    {
        return Generation::fromArray($this->send(
            fn (PendingRequest $http) => $http->withHeader('Idempotency-Key', $idempotencyKey)->post('generations', $request->toArray()),
        ));
    }

    public function find(string $id): Generation
    {
        return Generation::fromArray($this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($id))));
    }

    public function usage(CarbonInterface $from, CarbonInterface $to): array
    {
        $body = $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]));

        /** @var list<array<string, mixed>> $days */
        $days = $body['data'] ?? [];

        return array_map(UsageDay::fromArray(...), $days);
    }

    public function pricing(): Pricing
    {
        return Pricing::fromArray($this->send(fn (PendingRequest $http) => $http->get('pricing')));
    }

    public function handleWebhook(Request $request): Response
    {
        $secret = (string) config('services.pixelforge.webhook_secret');

        if ($secret === '') {
            Log::error('Pixelforge webhook refused: PIXELFORGE_WEBHOOK_SECRET is not set.');

            return response('invalid signature', Response::HTTP_BAD_REQUEST);
        }

        // Verify against the raw body, before any JSON decoding.
        $payload = $request->getContent();

        if (! $this->signatureValid($payload, (string) $request->header('Pixelforge-Signature'), $secret)) {
            return response('invalid signature', Response::HTTP_BAD_REQUEST);
        }

        /** @var array{id?: string, type?: string, data?: array{object?: array<string, mixed>}} $event */
        $event = json_decode($payload, true) ?: [];

        if (str_starts_with($event['type'] ?? '', 'generation.') && isset($event['id'], $event['data']['object'])) {
            GenerationUpdated::dispatch($event['id'], Generation::fromArray($event['data']['object']));
        }

        return response()->noContent();
    }

    private function signatureValid(string $payload, string $header, string $secret): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $parts['v1'] ?? '') && abs(time() - $timestamp) <= self::SIGNATURE_TOLERANCE;
    }

    /**
     * @param  callable(PendingRequest): ClientResponse  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $key = (string) config('services.pixelforge.key');

        if ($key === '') {
            throw new ProviderNotConfigured('PIXELFORGE_API_KEY is not set.');
        }

        try {
            $response = $call(Http::baseUrl((string) config('services.pixelforge.url'))->withToken($key)->acceptJson()->timeout(15));
        } catch (ConnectionException $e) {
            throw new ProviderUnavailable("Pixelforge unreachable: {$e->getMessage()}", $e);
        }

        if ($response->successful()) {
            /** @var array<string, mixed> */
            return $response->json() ?? [];
        }

        $code = $response->json('error.code');
        $message = 'Pixelforge: '.($response->json('error.message') ?? "HTTP {$response->status()}");
        $retryAfter = $response->header('Retry-After') !== '' ? (int) $response->header('Retry-After') : null;

        throw match (true) {
            $response->status() === 429 => new ProviderRateLimited($message, $retryAfter, $code),
            $response->serverError() => new ProviderUnavailable($message, retryAfter: $retryAfter),
            default => new ProviderRejected($message, $response->status(), $code, $response->json('error.type')),
        };
    }
}
