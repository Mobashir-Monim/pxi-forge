<?php

namespace App\Services\ImageGeneration\Data;

/** Token counts by type, as Pixelforge bills them. */
final class TokenUsage
{
    public function __construct(
        public readonly int $inputText,
        public readonly int $inputImage,
        public readonly int $output,
        public readonly int $reasoning,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inputText: (int) ($data['input_text_tokens'] ?? 0),
            inputImage: (int) ($data['input_image_tokens'] ?? 0),
            output: (int) ($data['output_tokens'] ?? 0),
            reasoning: (int) ($data['reasoning_tokens'] ?? 0),
        );
    }
}
