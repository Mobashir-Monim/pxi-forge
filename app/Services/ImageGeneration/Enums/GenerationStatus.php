<?php

namespace App\Services\ImageGeneration\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

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
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }
}
