<?php

namespace App\Domains\Generation\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Actions\Query\EstimateGenerationCost;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Domains\Generation\Jobs\SubmitGeneration;
use App\Domains\Generation\Models\Generation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sends a failed generation again as a new one with the same settings. It needs its own id, and so its own
 * Idempotency-Key: Pixelforge would answer the old key with the same failed job.
 */
final class RetryGeneration
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::GenerationsRetry->value);
    }

    public function handle(Generation $failed): Generation
    {
        if ($failed->status !== GenerationStatus::Failed || ! $failed->failure_reason?->retryable() || $failed->chat->isArchived()) {
            throw ValidationException::withMessages(['generation' => 'This generation can\'t be retried.']);
        }

        if ($failed->chat->generations()->latest('id')->value('id') !== $failed->id) {
            throw ValidationException::withMessages(['generation' => 'Only the latest generation in a chat can be retried.']);
        }

        if ($failed->chat->generations()->active()->exists()) {
            throw ValidationException::withMessages(['generation' => 'Wait for the current image to finish first.']);
        }

        // Re-estimated, since Pixelforge's rates may have changed since the first try.
        $estimate = EstimateGenerationCost::run($failed->prompt, $failed->size, $failed->quality, count($failed->references));

        try {
            $retry = $failed->chat->generations()->create([
                'user_id' => $failed->user_id,
                'retry_of_id' => $failed->id,
                'message' => $failed->message,
                'prompt' => $failed->prompt,
                'size' => $failed->size,
                'quality' => $failed->quality,
                'references' => $failed->references,
                'estimate_min_nd' => $estimate['min'],
                'estimate_max_nd' => $estimate['max'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double-clicked retry: the first one already made it, or the chat got busy in between.
            throw ValidationException::withMessages(['generation' => 'This generation was already retried.']);
        }

        $failed->chat->touch();
        SubmitGeneration::dispatch($retry->id);

        return $retry;
    }

    public function asController(ActionRequest $request, Generation $generation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($generation->user_id !== $user->id, 404);

        $this->handle($generation);

        return to_route('chats.show', $generation->chat_id);
    }
}
