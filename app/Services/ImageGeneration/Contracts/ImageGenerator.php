<?php

namespace App\Services\ImageGeneration\Contracts;

use App\Services\ImageGeneration\Data\Generation;
use App\Services\ImageGeneration\Data\GenerationRequest;
use App\Services\ImageGeneration\Data\Pricing;
use App\Services\ImageGeneration\Data\UsageDay;
use App\Services\Shared\Exceptions\ProviderRateLimited;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use Carbon\CarbonInterface;

/** Asynchronous text-to-image generation in app terms. */
interface ImageGenerator
{
    /**
     * Same key and same request returns the same generation, so a retry never creates a second one.
     *
     * @throws ProviderRejected content policy, validation, or a reused key
     * @throws ProviderRateLimited
     * @throws ProviderUnavailable
     */
    public function create(GenerationRequest $request, string $idempotencyKey): Generation;

    public function find(string $id): Generation;

    /**
     * Billed usage per UTC completion day, inclusive.
     *
     * @return list<UsageDay>
     */
    public function usage(CarbonInterface $from, CarbonInterface $to): array;

    public function pricing(): Pricing;
}
