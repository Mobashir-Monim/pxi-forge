<?php

namespace App\Domains\Generation\Actions\Query;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Models\Chat;
use App\Domains\Generation\Models\Generation;
use App\Models\User;
use App\Services\ObjectStore\ObjectStoreService;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

/** `/chats/{chat}`, the generate page with the chat's thread. Users only see what they're charged, never our cost. */
final class ShowChat
{
    use AsController;

    public function __construct(private readonly ObjectStoreService $store) {}

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::ChatsView->value);
    }

    public function asController(ActionRequest $request, Chat $chat): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($chat->user_id !== $user->id, 404);

        return Inertia::render('Dashboard', [
            'chat' => [
                'id' => $chat->id,
                'title' => $chat->title,
                'archived' => $chat->isArchived(),
            ],
            'generations' => $chat->generations()->oldest('id')->get()->map($this->present(...))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status->value,
            'message' => $generation->message,
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'references' => $generation->references,
            'estimate' => ['min' => $generation->estimate_min_nd, 'max' => $generation->estimate_max_nd],
            'charge' => $generation->charge_nd,
            'waiting_reason' => $generation->waiting_reason,
            'failure' => $generation->failure_reason === null ? null : [
                'reason' => $generation->failure_reason->value,
                'message' => $generation->failure_reason->message(),
                'retryable' => $generation->failure_reason->retryable(),
            ],
            'late' => $generation->timed_out_at !== null && $generation->completed_at !== null,
            'image_url' => $generation->image_path !== null ? $this->store->url($generation->image_path) : $generation->image_url,
            'retry_of_id' => $generation->retry_of_id,
            'created_at' => $generation->created_at->toIso8601ZuluString(),
            'accepted_at' => $generation->accepted_at?->toIso8601ZuluString(),
            'completed_at' => $generation->completed_at?->toIso8601ZuluString(),
        ];
    }
}
