<?php

namespace App\Services\ImageGeneration\Data;

/** What the provider billed for one UTC day, bucketed by completion day. */
final class UsageDay
{
    public function __construct(
        public readonly string $date,
        public readonly int $generations,
        public readonly TokenUsage $tokens,
        // Exact decimal string with 9 places, e.g. "0.169080000".
        public readonly string $costUsd,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            date: (string) $data['date'],
            generations: (int) $data['generations'],
            tokens: TokenUsage::fromArray($data),
            costUsd: (string) $data['cost_usd'],
        );
    }
}
