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

    /** Test helper: the provider id created for this idempotency key. */
    public static function idFor(string $idempotencyKey): string
    {
        return self::$keys[$idempotencyKey];
    }

    /**
     * Test helper: the generation as it looked with these fields, without storing it. For stale, out-of-order events.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function snapshot(string $id, array $overrides): Generation
    {
        return Generation::fromArray([...self::$generations[$id], ...$overrides]);
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
        // Bills what this fake completed, on its UTC completion day and at its own rates, as Pixelforge does.
        $days = [];

        for ($day = CarbonImmutable::parse($from)->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['generations' => 0, 'tokens' => [0, 0, 0, 0], 'cost' => '0'];
        }

        foreach (self::$generations as $generation) {
            $date = CarbonImmutable::parse($generation['completed_at'] ?? $generation['created_at'])->utc()->toDateString();

            if ($generation['status'] !== GenerationStatus::Succeeded->value || ! isset($days[$date])) {
                continue;
            }

            $days[$date]['generations']++;

            foreach (array_keys(self::RATES) as $i => $type) {
                $tokens = (int) $generation['usage'][$type];
                $days[$date]['tokens'][$i] += $tokens;
                $days[$date]['cost'] = bcadd($days[$date]['cost'], bcdiv(bcmul((string) $tokens, self::RATES[$type], 9), '1000000', 9), 9);
            }
        }

        return array_map(
            fn (string $date, array $day): UsageDay => new UsageDay($date, $day['generations'], new TokenUsage(...$day['tokens']), bcadd($day['cost'], '0', 9)),
            array_keys($days),
            $days,
        );
    }

    public function pricing(): Pricing
    {
        // Snapshot of the live GET /v1/pricing, 2026-11-24.
        return Pricing::fromArray([
            'rates' => self::RATES,
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ]);
    }
}
