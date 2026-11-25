<?php

namespace App\Domains\Generation\Actions\Command;

use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Domains\Generation\Jobs\StoreGeneratedImage;
use App\Domains\Generation\Models\Generation;
use App\Domains\Generation\Support\Money;
use App\Services\ImageGeneration\Data\Generation as ProviderGeneration;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use UnexpectedValueException;

/**
 * The one way Pixelforge's view of a generation reaches ours: create responses, webhooks and polls all come through
 * here. The row is locked, status only moves forward and money is written once, on the single move to a final state,
 * so duplicates, out-of-order events and a poll racing a webhook can't double charge.
 */
final class ApplyProviderUpdate
{
    use AsAction;

    public function handle(Generation $generation, ProviderGeneration $remote): Generation
    {
        $updated = DB::transaction(function () use ($generation, $remote): Generation {
            $local = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            $status = GenerationStatus::fromProvider($remote->status);

            // Facts that never change once Pixelforge has them, so any event may fill them in, even a stale one.
            $local->provider_id ??= $remote->id;
            $local->accepted_at ??= $remote->createdAt;
            $local->rates ??= $remote->rates->toArray();
            $local->started_at ??= $remote->startedAt;

            // Pixelforge never succeeds a job it failed, so this means we gave up on our side: the time limit ran out, or a
            // create we thought was lost had reached it. The user was told it was free, so it shows up and stays free.
            $lateSuccess = $local->status === GenerationStatus::Failed && $status === GenerationStatus::Succeeded;

            if ($status->isAfter($local->status) || $lateSuccess) {
                $local->status = $status;
                $local->waiting_reason = null;

                if ($status === GenerationStatus::Succeeded) {
                    $this->succeed($local, $remote, $lateSuccess);
                } elseif ($status === GenerationStatus::Failed) {
                    $local->completed_at = $remote->completedAt;
                    $local->markFailed(FailureReason::fromProviderCode($remote->errorCode), $remote->errorMessage);
                }
            }

            $local->save();

            return $local;
        });

        if ($updated->status === GenerationStatus::Succeeded && $updated->image_path === null && $updated->wasChanged('status')) {
            StoreGeneratedImage::dispatch($updated->id);
        }

        return $updated;
    }

    private function succeed(Generation $local, ProviderGeneration $remote, bool $late): void
    {
        $usage = $remote->usage ?? throw new UnexpectedValueException("Generation {$remote->id} succeeded without usage.");
        $cost = Money::providerCost($usage, $remote->rates);

        $local->forceFill([
            'input_text_tokens' => $usage->inputText,
            'input_image_tokens' => $usage->inputImage,
            'output_tokens' => $usage->output,
            'reasoning_tokens' => $usage->reasoning,
            'provider_cost_nd' => $cost,
            'charge_nd' => $late ? 0 : Money::charge($cost),
            'failure_reason' => null,
            'error_message' => null,
            'image_url' => $remote->imageUrl,
            'image_url_expires_at' => $remote->imageUrlExpiresAt,
            'completed_at' => $remote->completedAt,
        ]);
    }
}
