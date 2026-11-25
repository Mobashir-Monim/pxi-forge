<?php

namespace App\Domains\Generation\Actions\Command;

use App\Domains\Authorization\Enums\Permission;
use App\Models\User;
use App\Services\ObjectStore\ObjectStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/** Pixelforge takes reference images as public URLs only, so uploads go to object storage first. */
final class UploadReference
{
    use AsAction;

    public function __construct(private readonly ObjectStoreService $store) {}

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::ReferencesUpload->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:10240'],
        ];
    }

    /** @return array{url: string, name: string} */
    public function handle(User $user, UploadedFile $file): array
    {
        $key = "references/{$user->id}/".Str::lower((string) Str::ulid()).'.'.$file->extension();
        $stored = $this->store->put($key, (string) $file->get(), $file->getMimeType());

        return ['url' => $stored->url, 'name' => $file->getClientOriginalName()];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json($this->handle($user, $file), 201);
    }
}
