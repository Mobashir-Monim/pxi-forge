<?php

use App\Domains\Generation\Actions\Query\EstimateGenerationCost;
use App\Models\User;
use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;

test('matches the worked example in the Pixelforge docs, plus the 25% markup', function () {
    // Docs: high, 1536x1024, 400-char prompt, 2 references costs Pixelforge at most $0.373875.
    $estimate = EstimateGenerationCost::run(str_repeat('a', 400), ImageSize::Landscape, ImageQuality::High, 2);

    // Min: 85 text, 1,530 image, 6,450 output, 1,000 reasoning tokens = $0.286225 before markup.
    expect($estimate)->toBe(['min' => 357_781_250, 'max' => 467_343_750]);
});

test('counts prompt characters, not bytes, and rounds the text range outward', function () {
    // 10 multibyte characters = ceil(10/4) = 3 tokens, range floor(2.55)=2 to ceil(3.45)=4.
    $estimate = EstimateGenerationCost::run(str_repeat('é', 10), ImageSize::Square, ImageQuality::Low, 0);

    // Low square: 280 output tokens × $40/1M = $0.0112, reasoning 0..500 × $12.50/1M.
    $min = (2 * 5_000 + 280 * 40_000) * 125 / 100;
    $max = (4 * 5_000 + 280 * 40_000 + 500 * 12_500) * 125 / 100;

    expect($estimate)->toBe(['min' => (int) $min, 'max' => (int) $max]);
});

test('serves the estimate to signed-in users as JSON', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('generations.estimate', ['prompt' => 'a fox', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => 1]))
        ->assertOk()
        ->assertJsonStructure(['min', 'max']);
});

test('rejects more reference images than Pixelforge accepts, and unknown sizes', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('generations.estimate', ['size' => '512x512', 'quality' => 'medium', 'reference_images' => 5]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size', 'reference_images']);
});

test('guests cannot fetch estimates', function () {
    $this->getJson(route('generations.estimate'))->assertUnauthorized();
});

test('reads cached pricing back from the database cache', function () {
    config(['cache.default' => 'database']);

    EstimateGenerationCost::run('a fox', ImageSize::Square, ImageQuality::Medium, 0);

    expect(EstimateGenerationCost::run('a fox', ImageSize::Square, ImageQuality::Medium, 0))->toHaveKeys(['min', 'max']);
});
