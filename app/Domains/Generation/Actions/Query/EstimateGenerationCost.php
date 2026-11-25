<?php

namespace App\Domains\Generation\Actions\Query;

use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;
use App\Services\ImageGeneration\ImageGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a generation will cost the user, as a range. Text and reasoning token counts are only known once Pixelforge
 * finishes, so the range runs from the fewest tokens it can bill to the most. Everything is integer nanodollars
 * ($0.000000001), the user's price including the markup, never Pixelforge's own cost.
 */
final class EstimateGenerationCost
{
    use AsAction;

    public const int MARKUP_PERCENT = 125;

    public const int MAX_REFERENCE_IMAGES = 4;

    public function __construct(private readonly ImageGenerationService $images) {}

    /**
     * @return array{min: int, max: int}
     */
    public function handle(string $prompt, ImageSize $size, ImageQuality $quality, int $referenceImages): array
    {
        $pricing = $this->images->pricing();
        $tokens = $pricing->tokens;

        $text = (int) ceil(mb_strlen($prompt) / $tokens['characters_per_text_token']);
        $varianceBp = (int) round($tokens['text_token_variance'] * 10_000);
        $reasoning = $tokens['reasoning'][$quality->value];

        // Exact for every token count, so it's the same at both ends of the range.
        $fixed = $referenceImages * $tokens['per_reference_image'] * self::perMillion($pricing->rates->inputImage)
            + $tokens['output'][$quality->value][$size->value] * self::perMillion($pricing->rates->output);

        // Rounded outward, so the real count always lands inside the range.
        $minText = intdiv($text * (10_000 - $varianceBp), 10_000);
        $maxText = self::ceilDiv($text * (10_000 + $varianceBp), 10_000);

        return [
            'min' => self::charge($fixed
                + $minText * self::perMillion($pricing->rates->inputText)
                + $reasoning['min'] * self::perMillion($pricing->rates->reasoning)),
            'max' => self::charge($fixed
                + $maxText * self::perMillion($pricing->rates->inputText)
                + $reasoning['max'] * self::perMillion($pricing->rates->reasoning)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['required', 'integer', 'min:0', 'max:'.self::MAX_REFERENCE_IMAGES],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle(
            (string) $request->validated('prompt'),
            ImageSize::from($request->validated('size')),
            ImageQuality::from($request->validated('quality')),
            (int) $request->validated('reference_images'),
        ));
    }

    /**
     * A "USD per 1M tokens" decimal string as integer nanodollars per 1M tokens, so "12.50" is 12,500,000,000. Parsed
     * from the string, never through a float.
     */
    private static function perMillion(string $rate): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,9}))?$/', $rate, $m) !== 1) {
            throw new InvalidArgumentException("Unreadable token rate: {$rate}");
        }

        return (int) $m[1] * 1_000_000_000 + (int) str_pad($m[2] ?? '', 9, '0');
    }

    /**
     * Pixelforge's cost (summed as nanodollars per 1M tokens × tokens) plus the markup, in nanodollars.
     * ponytail: rounds up to the next nanodollar, exact with today's rates. A rate with more decimals than they have
     * would make this round, always in our favour by under $0.000000001.
     */
    private static function charge(int $perMillionTotal): int
    {
        return self::ceilDiv($perMillionTotal * self::MARKUP_PERCENT, 100 * 1_000_000);
    }

    private static function ceilDiv(int $a, int $b): int
    {
        return intdiv($a + $b - 1, $b);
    }
}
