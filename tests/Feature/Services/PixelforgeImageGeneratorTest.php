<?php

use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Enums\GenerationStatus;
use App\Services\ImageGeneration\Enums\ImageGenerationProviders;
use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Events\GenerationUpdated;
use App\Services\ImageGeneration\ImageGenerationService;
use App\Services\Shared\Exceptions\ProviderRateLimited;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.pixelforge.key' => 'pk_test', 'services.pixelforge.webhook_secret' => 'whsec_test']);
});

function pixelforge(): ImageGenerationService
{
    return app(ImageGenerationService::class);
}

/** @return array<string, mixed> */
function generationJson(array $overrides = []): array
{
    return [
        'id' => 'gen_1', 'object' => 'generation', 'status' => 'succeeded', 'prompt' => 'a cat', 'size' => '1024x1024',
        'quality' => 'high', 'reference_images' => [], 'metadata' => ['generation_id' => '42'],
        'image_url' => 'https://img/gen_1.png', 'image_url_expires_at' => '2026-11-05T14:03:29Z',
        'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 0, 'output_tokens' => 4300, 'reasoning_tokens' => 3184, 'total_tokens' => 7496],
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'error' => null, 'created_at' => '2026-11-04T14:03:11Z', 'started_at' => '2026-11-04T14:03:13Z', 'completed_at' => '2026-11-04T14:03:29Z',
        ...$overrides,
    ];
}

function signed(string $body, ?int $at = null, string $secret = 'whsec_test'): array
{
    $at ??= time();

    return ['Pixelforge-Signature' => "t={$at},v1=".hash_hmac('sha256', "{$at}.{$body}", $secret), 'Content-Type' => 'application/json'];
}

it('creates a generation with the idempotency key and parses it', function () {
    Http::fake(['*/generations' => Http::response(generationJson(), 202)]);

    $generation = pixelforge()->create(
        new GenerationRequest('a cat', quality: ImageQuality::High, metadata: ['generation_id' => '42']),
        'key-1',
        ImageGenerationProviders::Pixelforge,
    );

    Http::assertSent(fn (Request $r) => $r->hasHeader('Idempotency-Key', 'key-1')
        && $r->hasHeader('Authorization', 'Bearer pk_test')
        && $r->data() === ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'high', 'metadata' => ['generation_id' => '42']]);

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->usage->reasoning)->toBe(3184)
        ->and($generation->rates->output)->toBe('40.00')
        ->and($generation->completedAt->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');
});

it('maps provider errors to app exceptions', function (int $status, string $code, array $headers, string $exception) {
    Http::fake(['*' => Http::response(['error' => ['type' => 'x', 'code' => $code, 'message' => 'no']], $status, $headers)]);

    expect(fn () => pixelforge()->create(new GenerationRequest('a cat'), 'key-1', ImageGenerationProviders::Pixelforge))
        ->toThrow($exception);
})->with([
    'rate limited' => [429, 'rate_limit_exceeded', ['Retry-After' => '7'], ProviderRateLimited::class],
    'overloaded' => [503, 'overloaded', ['Retry-After' => '2'], ProviderUnavailable::class],
    'content policy' => [400, 'content_policy_violation', [], ProviderRejected::class],
]);

it('keeps retry-after and the error code on a rate limit', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'concurrency_limit_exceeded', 'message' => 'busy']], 429, ['Retry-After' => '5'])]);

    try {
        pixelforge()->create(new GenerationRequest('a cat'), 'key-1', ImageGenerationProviders::Pixelforge);
    } catch (ProviderRateLimited $e) {
        expect($e->retryAfter)->toBe(5)->and($e->errorCode)->toBe('concurrency_limit_exceeded');

        return;
    }

    test()->fail('Expected ProviderRateLimited.');
});

it('reads usage per day', function () {
    Http::fake(['*/usage*' => Http::response(['data' => [
        ['date' => '2026-11-01', 'generations' => 3, 'input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'cost_usd' => '0.169080000'],
    ]])]);

    $days = pixelforge()->usage(now()->parse('2026-11-01'), now()->parse('2026-11-01'), ImageGenerationProviders::Pixelforge);

    expect($days)->toHaveCount(1)->and($days[0]->costUsd)->toBe('0.169080000');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'start_date=2026-11-01&end_date=2026-11-01'));
});

it('dispatches a verified webhook', function () {
    Event::fake([GenerationUpdated::class]);
    $body = json_encode(['id' => 'evt_1', 'type' => 'generation.succeeded', 'data' => ['object' => generationJson()]]);

    $this->call('POST', '/webhooks/imageGeneration/pixelforge', server: $this->transformHeadersToServerVars(signed($body)), content: $body)
        ->assertNoContent();

    Event::assertDispatched(GenerationUpdated::class, fn (GenerationUpdated $e) => $e->eventId === 'evt_1' && $e->generation->id === 'gen_1');
});

it('refuses a webhook with a bad or stale signature', function (Closure $headers) {
    Event::fake([GenerationUpdated::class]);
    $body = json_encode(['id' => 'evt_1', 'type' => 'generation.succeeded', 'data' => ['object' => generationJson()]]);

    $this->call('POST', '/webhooks/imageGeneration/pixelforge', server: $this->transformHeadersToServerVars($headers($body)), content: $body)
        ->assertBadRequest();

    Event::assertNotDispatched(GenerationUpdated::class);
})->with([
    'wrong secret' => [fn (string $body) => signed($body, secret: 'whsec_other')],
    'stale' => [fn (string $body) => signed($body, time() - 301)],
    'missing' => [fn (string $body) => []],
]);

it('replays an idempotent create on the fake', function () {
    $first = pixelforge()->create(new GenerationRequest('a cat'), 'key-1');

    expect(pixelforge()->create(new GenerationRequest('a cat'), 'key-1')->id)->toBe($first->id)
        ->and(fn () => pixelforge()->create(new GenerationRequest('[blocked] cat'), 'key-2'))->toThrow(ProviderRejected::class);
});
