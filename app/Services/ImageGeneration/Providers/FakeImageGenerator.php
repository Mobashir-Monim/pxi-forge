<?php

namespace App\Services\ImageGeneration\Providers;

use App\Services\ImageGeneration\Contracts\ImageGenerator;
use App\Services\ImageGeneration\Data\Generation;
use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Data\Pricing;
use App\Services\ImageGeneration\Data\TokenUsage;
use App\Services\ImageGeneration\Data\UsageDay;
use App\Services\ImageGeneration\Enums\GenerationStatus;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * In-memory Pixelforge for tests and offline dev. Honours the [blocked] and [overloaded] prompt keywords and idempotency keys.
 * Generations stay queued until a test calls complete() or fail().
 */
final class FakeImageGenerator implements ImageGenerator
{
    private const RATES = ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'];

    /** @var array<string, array<string, mixed>> generation arrays by id */
    private static array $generations = [];

    /** @var array<string, string> generation id by idempotency key */
    private static array $keys = [];

    public static function flush(): void
    {
        self::$generations = [];
        self::$keys = [];
    }

    public function create(GenerationRequest $request, string $idempotencyKey): Generation
    {
        if (isset(self::$keys[$idempotencyKey])) {
            return $this->find(self::$keys[$idempotencyKey]);
        }

        if (Str::contains($request->prompt, '[blocked]', ignoreCase: true)) {
            throw new ProviderRejected('Pixelforge: prompt rejected.', 400, 'content_policy_violation', 'invalid_request_error');
        }

        if (Str::contains($request->prompt, '[overloaded]', ignoreCase: true)) {
            throw new ProviderUnavailable('Pixelforge: overloaded.', retryAfter: 2);
        }

        $id = 'gen_'.Str::upper((string) Str::ulid());

        self::$keys[$idempotencyKey] = $id;
        self::$generations[$id] = [
            'id' => $id,
            'status' => GenerationStatus::Queued->value,
            ...$request->toArray(),
            'metadata' => $request->metadata,
            'rates' => self::RATES,
            'usage' => null,
            'error' => null,
            'created_at' => now()->toIso8601ZuluString(),
        ];

        return $this->find($id);
    }

    public function find(string $id): Generation
    {
        $data = self::$generations[$id] ?? throw new ProviderRejected('Pixelforge: no such generation.', 404, 'not_found');

        return Generation::fromArray($data);
    }

    /** Test helper: finish a generation as succeeded with these token counts. */
    public static function complete(string $id, TokenUsage $usage): Generation
    {
        self::$generations[$id] = [
            ...self::$generations[$id],
            'status' => GenerationStatus::Succeeded->value,
            'image_url' => "https://pixelforge.fake/images/{$id}.png",
            'image_url_expires_at' => now()->addDay()->toIso8601ZuluString(),
            'usage' => [
                'input_text_tokens' => $usage->inputText,
                'input_image_tokens' => $usage->inputImage,
                'output_tokens' => $usage->output,
                'reasoning_tokens' => $usage->reasoning,
            ],
            'completed_at' => now()->toIso8601ZuluString(),
        ];

        return Generation::fromArray(self::$generations[$id]);
    }

    /** Test helper: finish a generation as failed. */
    public static function fail(string $id, string $code = 'generation_failed'): Generation
    {
        self::$generations[$id] = [
            ...self::$generations[$id],
            'status' => GenerationStatus::Failed->value,
            'error' => ['code' => $code, 'message' => 'Fake failure.'],
            'completed_at' => now()->toIso8601ZuluString(),
        ];

        return Generation::fromArray(self::$generations[$id]);
    }

    public function usage(CarbonInterface $from, CarbonInterface $to): array
    {
        // ponytail: zero days only; the admin report tests seed their own expectations.
        $days = [];

        for ($day = CarbonImmutable::parse($from)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $days[] = new UsageDay($day->toDateString(), 0, new TokenUsage(0, 0, 0, 0), '0.000000000');
        }

        return $days;
    }

    public function pricing(): Pricing
    {
        return Pricing::fromArray((array) json_decode((string) file_get_contents(__DIR__.'/fake-pricing.json'), true));
    }
}
