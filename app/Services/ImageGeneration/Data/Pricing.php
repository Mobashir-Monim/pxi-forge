<?php

namespace App\Services\ImageGeneration\Data;

/** Current rates plus the token tables an estimate needs. */
final class Pricing
{
    /**
     * @param  array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}  $tokens
     */
    public function __construct(
        public readonly TokenRates $rates,
        public readonly array $tokens,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $rates */
        $rates = $data['rates'];
        /** @var array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>} $tokens */
        $tokens = $data['tokens'];

        return new self(TokenRates::fromArray($rates), $tokens);
    }
}
