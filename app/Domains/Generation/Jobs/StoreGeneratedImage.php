<?php

namespace App\Domains\Generation\Jobs;

use App\Domains\Generation\Models\Generation;
use App\Services\ObjectStore\ObjectStoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Pixelforge's image links die 24 hours after completion. A stored copy keeps the image usable as a reference later. */
final class StoreGeneratedImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $generationId) {}

    public function handle(ObjectStoreService $store): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation?->image_url === null || $generation->image_path !== null) {
            return;
        }

        $png = Http::timeout(30)->get($generation->image_url)->throw()->body();
        $key = "generations/{$generation->id}.png";

        $store->put($key, $png, 'image/png');
        $generation->update(['image_path' => $key]);
    }
}
