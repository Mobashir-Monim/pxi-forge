<?php

namespace App\Services\ImageGeneration\Events;

use App\Services\ImageGeneration\Data\Generation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A verified provider webhook reported a generation's state. Delivery is at least once and in any order,
 * so listeners dedupe by $eventId and only move a generation forward.
 */
final class GenerationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $eventId,
        public readonly Generation $generation,
    ) {}
}
