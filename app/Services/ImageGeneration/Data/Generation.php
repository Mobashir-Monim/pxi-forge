<?php

namespace App\Services\ImageGeneration\Data;

use App\Services\ImageGeneration\Enums\GenerationStatus;
use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;
use Carbon\CarbonImmutable;

/** A generation as the provider last reported it. */
final class Generation
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly GenerationStatus $status,
        public readonly string $prompt,
        public readonly ImageSize $size,
        public readonly ImageQuality $quality,
        public readonly array $metadata,
        public readonly TokenRates $rates,
        public readonly ?TokenUsage $usage,
        public readonly ?string $imageUrl,
        public readonly ?CarbonImmutable $imageUrlExpiresAt,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $completedAt,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $usage */
        $usage = $data['usage'] ?? null;
        /** @var array<string, mixed> $rates */
        $rates = $data['rates'];
        /** @var array{code?: string, message?: string}|null $error */
        $error = $data['error'] ?? null;
        /** @var array<string, string> $metadata */
        $metadata = $data['metadata'] ?? [];

        return new self(
            id: (string) $data['id'],
            status: GenerationStatus::from((string) $data['status']),
            prompt: (string) $data['prompt'],
            size: ImageSize::from((string) $data['size']),
            quality: ImageQuality::from((string) $data['quality']),
            metadata: $metadata,
            rates: TokenRates::fromArray($rates),
            usage: $usage !== null ? TokenUsage::fromArray($usage) : null,
            imageUrl: isset($data['image_url']) ? (string) $data['image_url'] : null,
            imageUrlExpiresAt: self::time($data['image_url_expires_at'] ?? null),
            errorCode: $error['code'] ?? null,
            errorMessage: $error['message'] ?? null,
            createdAt: CarbonImmutable::parse((string) $data['created_at']),
            startedAt: self::time($data['started_at'] ?? null),
            completedAt: self::time($data['completed_at'] ?? null),
        );
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
