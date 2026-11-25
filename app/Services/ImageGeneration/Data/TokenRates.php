<?php

namespace App\Services\ImageGeneration\Data;

/** USD per 1M tokens, kept as the exact decimal strings Pixelforge sends. Never parse these to float. */
final class TokenRates
{
    public function __construct(
        public readonly string $inputText,
        public readonly string $inputImage,
        public readonly string $output,
        public readonly string $reasoning,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            inputText: (string) $data['input_text_tokens'],
            inputImage: (string) $data['input_image_tokens'],
            output: (string) $data['output_tokens'],
            reasoning: (string) $data['reasoning_tokens'],
        );
    }
}
