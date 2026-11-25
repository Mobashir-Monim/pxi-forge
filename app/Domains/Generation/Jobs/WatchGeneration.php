<?php

namespace App\Domains\Generation\Jobs;

use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Models\Generation;
use App\Services\ImageGeneration\ImageGenerationService;
use App\Services\Shared\Exceptions\ProviderException;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Webhooks are the main signal. This is the fallback for when they're late or lost: counted from when Pixelforge
 * accepted the job, it polls at 90s, then every 30s from 120s, and at 300s gives up and fails the generation for free.
 */
final class WatchGeneration implements ShouldQueue
{
    use Queueable;

    public const int FIRST_CHECK = 90;

    public const int STEADY_FROM = 120;

    public const int INTERVAL = 30;

    public const int TIME_LIMIT = 300;

    public function __construct(public readonly string $generationId) {}

    public static function nextCheck(Generation $generation): CarbonInterface
    {
        $accepted = $generation->accepted_at ?? now();
        $elapsed = (int) $accepted->diffInSeconds(now());

        $next = match (true) {
            $elapsed < self::FIRST_CHECK => self::FIRST_CHECK,
            $elapsed < self::STEADY_FROM => self::STEADY_FROM,
            default => min($elapsed + self::INTERVAL, self::TIME_LIMIT),
        };

        return $accepted->addSeconds($next);
    }

    public function handle(ImageGenerationService $images): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $generation->status->isFinal() || $generation->provider_id === null) {
            return;
        }

        try {
            $generation = ApplyProviderUpdate::run($generation, $images->find($generation->provider_id));
        } catch (ProviderException $e) {
            report($e); // A missed poll only costs one check, the next one or a webhook still lands.
        }

        if ($generation->status->isFinal()) {
            return;
        }

        if ($generation->accepted_at !== null && $generation->accepted_at->addSeconds(self::TIME_LIMIT)->isPast()) {
            $this->timeOut($generation);

            return;
        }

        self::dispatch($generation->id)->delay(self::nextCheck($generation));
    }

    /** Locked, so a webhook landing at the same moment either wins cleanly or finds the timeout already written. */
    private function timeOut(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            $local = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            if ($local->status->isFinal()) {
                return;
            }

            $local->timed_out_at = now();
            $local->markFailed(FailureReason::TimedOut);
        });
    }
}
