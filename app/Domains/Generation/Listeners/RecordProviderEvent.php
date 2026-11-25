<?php

namespace App\Domains\Generation\Listeners;

use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Models\Generation;
use App\Services\ImageGeneration\Events\GenerationUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified webhook. Runs inside the webhook request: it's a couple of row writes, and if it throws, the
 * non-2xx answer makes Pixelforge deliver the event again. The event row and the update commit together, so a
 * failed apply never leaves the event marked as handled.
 */
final class RecordProviderEvent
{
    public function handle(GenerationUpdated $event): void
    {
        DB::transaction(function () use ($event): void {
            $recorded = DB::table('webhook_events')->insertOrIgnore([
                'id' => $event->eventId,
                'provider_id' => $event->generation->id,
                'status' => $event->generation->status->value,
                'created_at' => now(),
            ]);

            if ($recorded === 0) {
                return; // Delivered before. Already applied.
            }

            // The metadata id works even when the webhook beats the create response, before we know Pixelforge's id.
            $generation = Generation::query()->find($event->generation->metadata['generation_id'] ?? null)
                ?? Generation::query()->where('provider_id', $event->generation->id)->first();

            if ($generation === null) {
                Log::warning('Pixelforge event for an unknown generation.', ['event' => $event->eventId, 'generation' => $event->generation->id]);

                return;
            }

            ApplyProviderUpdate::run($generation, $event->generation);
        });
    }
}
