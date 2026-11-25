<?php

namespace App\Services\ImageGeneration;

use App\Services\ImageGeneration\Contracts\ImageGenerator;
use App\Services\ImageGeneration\Data\Generation;
use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Data\Pricing;
use App\Services\ImageGeneration\Data\UsageDay;
use App\Services\ImageGeneration\Enums\ImageGenerationProviders;
use App\Services\Shared\ProviderRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/** ImageGeneration service root, the public API the codebase calls. */
final class ImageGenerationService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function create(GenerationRequest $request, string $idempotencyKey, ?ImageGenerationProviders $using = null): Generation
    {
        return $this->provider($using)->create($request, $idempotencyKey);
    }

    public function find(string $id, ?ImageGenerationProviders $using = null): Generation
    {
        return $this->provider($using)->find($id);
    }

    /** @return list<UsageDay> */
    public function usage(CarbonInterface $from, CarbonInterface $to, ?ImageGenerationProviders $using = null): array
    {
        return $this->provider($using)->usage($from, $to);
    }

    /**
     * Current rates, cached for estimates only. Billing never uses these: each generation carries its own rates.
     */
    public function pricing(?ImageGenerationProviders $using = null): Pricing
    {
        return Cache::remember(
            'image-generation.pricing.'.($using->value ?? 'default'),
            now()->addMinutes(10),
            fn (): Pricing => $this->provider($using)->pricing(),
        );
    }

    private function provider(?ImageGenerationProviders $using): ImageGenerator
    {
        /** @var ImageGenerator */
        return $this->registry->resolve('imageGeneration', $using?->value);
    }
}
