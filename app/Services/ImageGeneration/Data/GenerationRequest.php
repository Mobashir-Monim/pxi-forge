<?php

namespace App\Services\ImageGeneration\Data;

use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;

final class GenerationRequest
{
    /**
     * @param  list<string>  $referenceImages  http(s) URLs, at most 4
     * @param  array<string, string>  $metadata  echoed back on the generation and every webhook
     */
    public function __construct(
        public readonly string $prompt,
        public readonly ImageSize $size = ImageSize::Square,
        public readonly ImageQuality $quality = ImageQuality::Medium,
        public readonly array $referenceImages = [],
        public readonly array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->referenceImages,
            'metadata' => $this->metadata,
        ], fn (mixed $value): bool => $value !== []);
    }
}
