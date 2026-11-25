<?php

namespace App\Domains\Generation\Jobs;

use App\Domains\Generation\Actions\Command\ApplyProviderUpdate;
use App\Domains\Generation\Enums\FailureReason;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Domains\Generation\Models\Generation;
use App\Services\ImageGeneration\ImageGenerationService;
use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\Shared\Exceptions\ProviderRateLimited;
use App\Services\Shared\Exceptions\ProviderRejected;
use App\Services\Shared\Exceptions\ProviderUnavailable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hands a pending generation to Pixelforge. The generation's id is the Idempotency-Key, so running this twice, or
 * retrying after a timeout that Pixelforge actually received, returns the same job there instead of a second one.
 */
final class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Overloads are retried this many times in all, then the user gets a retry button. */
    public const int MAX_ATTEMPTS = 4;

    /** How long to keep waiting for a free slot (3 at once per account) before giving up. */
    public const int MAX_WAIT_SECONDS = 300;

    public function __construct(public readonly string $generationId) {}

    /** Above MAX_WAIT_SECONDS, so our own give-up path runs before the queue's. */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds(self::MAX_WAIT_SECONDS + 120);
    }

    public function handle(ImageGenerationService $images): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation?->status !== GenerationStatus::Pending) {
            return; // Already submitted, or failed while waiting.
        }

        $generation->increment('submit_attempts');

        try {
            $remote = $images->create($generation->toRequest(), $generation->id);
        } catch (ProviderRateLimited $e) {
            if ($e->errorCode === 'daily_limit_exceeded') {
                $generation->markFailed(FailureReason::DailyLimit, $e->getMessage());

                return;
            }

            // Too many requests this minute, or 3 generations already running on the account. Both clear on their own.
            $this->waitOrFail($generation, FailureReason::Busy, $e->retryAfter ?? 5, $e->getMessage());

            return;
        } catch (ProviderUnavailable $e) {
            // ponytail: attempts count every try, busy waits included, so an overload after a long wait gets fewer retries.
            if ($generation->submit_attempts >= self::MAX_ATTEMPTS) {
                $generation->markFailed(FailureReason::Overloaded, $e->getMessage());

                return;
            }

            $this->waitOrFail($generation, FailureReason::Overloaded, max($e->retryAfter ?? 0, 2 ** $generation->submit_attempts), $e->getMessage());

            return;
        } catch (ProviderRejected $e) {
            $reason = $e->errorCode === 'content_policy_violation' ? FailureReason::Blocked : FailureReason::Rejected;
            $generation->markFailed($reason, $e->getMessage());

            if ($reason === FailureReason::Blocked) {
                $generation->chat->archive($reason->value);
            }

            if ($reason === FailureReason::Rejected) {
                report($e); // A 422 or 401 is our bug or our setup, not the user's.
            }

            return;
        } catch (ProviderNotConfigured $e) {
            $generation->markFailed(FailureReason::Rejected, $e->getMessage());
            report($e);

            return;
        }

        $generation = ApplyProviderUpdate::run($generation, $remote);

        if (! $generation->status->isFinal()) {
            WatchGeneration::dispatch($generation->id)->delay(WatchGeneration::nextCheck($generation));
        }
    }

    private function waitOrFail(Generation $generation, FailureReason $reason, int $seconds, string $detail): void
    {
        if ($generation->created_at->addSeconds(self::MAX_WAIT_SECONDS)->isPast()) {
            $generation->markFailed($reason, $detail);

            return;
        }

        $generation->update(['waiting_reason' => $reason->value]);
        $this->release($seconds);
    }
}
