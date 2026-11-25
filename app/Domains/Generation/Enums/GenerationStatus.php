<?php

namespace App\Domains\Generation\Enums;

use App\Services\ImageGeneration\Enums\GenerationStatus as ProviderStatus;

/** Pixelforge's lifecycle with one step in front: pending is ours, before Pixelforge has accepted the request. */
enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** Statuses that hold the chat, through the unique generations.active_chat_id. */
    public const array ACTIVE = ['pending', 'queued', 'processing'];

    public static function fromProvider(ProviderStatus $status): self
    {
        return self::from($status->value);
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /** Status only moves forward, so a late or replayed event never drags a generation back. */
    public function isAfter(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
