<?php

namespace App\Domains\Generation\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Actions\Query\EstimateGenerationCost;
use App\Domains\Generation\Jobs\SubmitGeneration;
use App\Domains\Generation\Models\Chat;
use App\Domains\Generation\Models\Generation;
use App\Models\User;
use App\Services\ImageGeneration\Enums\ImageQuality;
use App\Services\ImageGeneration\Enums\ImageSize;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sends a prompt, in a new chat or as the next turn of an existing one. The row is saved before Pixelforge hears of
 * it, so a webhook can never arrive for a generation we don't know. Duplicates are stopped by the database: one row
 * per click (client_request_id) and one running generation per chat.
 */
final class SendGeneration
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::GenerationsCreate->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'ulid'],
            'message' => ['required', 'string', 'max:4000'],
            // What Pixelforge receives: the message with each reference note appended, composed by the browser.
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'references' => ['nullable', 'array', 'max:'.EstimateGenerationCost::MAX_REFERENCE_IMAGES],
            'references.*.url' => ['required', 'url:http,https', 'max:2048'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.annotations' => ['nullable', 'array', 'max:26'],
            'references.*.annotations.*.x' => ['required', 'numeric', 'between:0,1'],
            'references.*.annotations.*.y' => ['required', 'numeric', 'between:0,1'],
            'references.*.annotations.*.width' => ['required', 'numeric', 'between:0,1'],
            'references.*.annotations.*.height' => ['required', 'numeric', 'between:0,1'],
            'references.*.annotations.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array{client_request_id: string, message: string, prompt: string, size: string, quality: string, references?: array<array-key, array{url: string, name?: string|null, annotations?: array<array-key, array<string, mixed>>|null}>|null}  $input
     */
    public function handle(User $user, ?Chat $chat, array $input): Generation
    {
        // The same click again (double submit, or a resend after a dropped response) gets the generation it made.
        $existing = Generation::query()->where('client_request_id', $input['client_request_id'])->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($chat?->isArchived()) {
            throw ValidationException::withMessages(['message' => 'This chat is closed. Start a new one.']);
        }

        if ($chat?->generations()->active()->exists()) {
            throw ValidationException::withMessages(['message' => 'Wait for the current image to finish first.']);
        }

        $references = array_map(fn (array $reference): array => [
            'url' => $reference['url'],
            'name' => $reference['name'] ?? '',
            'annotations' => array_values($reference['annotations'] ?? []),
        ], array_values($input['references'] ?? []));

        $size = ImageSize::from($input['size']);
        $quality = ImageQuality::from($input['quality']);
        $estimate = EstimateGenerationCost::run($input['prompt'], $size, $quality, count($references));

        try {
            $generation = DB::transaction(function () use ($user, $chat, $input, $references, $size, $quality, $estimate): Generation {
                $chat ??= Chat::query()->create(['user_id' => $user->id, 'title' => Str::limit($input['message'], 60)]);
                $chat->touch();

                return $chat->generations()->create([
                    'user_id' => $user->id,
                    'client_request_id' => $input['client_request_id'],
                    'message' => $input['message'],
                    'prompt' => $input['prompt'],
                    'size' => $size,
                    'quality' => $quality,
                    'references' => $references,
                    'estimate_min_nd' => $estimate['min'],
                    'estimate_max_nd' => $estimate['max'],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a twin request: either the same click, or another send into this chat.
            return Generation::query()->where('client_request_id', $input['client_request_id'])->where('user_id', $user->id)->first()
                ?? throw ValidationException::withMessages(['message' => 'Wait for the current image to finish first.']);
        }

        SubmitGeneration::dispatch($generation->id);

        return $generation;
    }

    public function asController(ActionRequest $request, ?Chat $chat = null): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($chat !== null && $chat->user_id !== $user->id, 404);

        /** @var array{client_request_id: string, message: string, prompt: string, size: string, quality: string, references?: array<array-key, array{url: string, name?: string|null, annotations?: array<array-key, array<string, mixed>>|null}>|null} $input */
        $input = $request->validated();
        $generation = $this->handle($user, $chat, $input);

        return to_route('chats.show', $generation->chat_id);
    }
}
