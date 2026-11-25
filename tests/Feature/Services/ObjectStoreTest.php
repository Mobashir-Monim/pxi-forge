<?php

use App\Services\ObjectStore\Enums\ObjectStoreProviders;
use App\Services\ObjectStore\Exceptions\ObjectNotFound;
use App\Services\ObjectStore\ObjectStoreService;
use Illuminate\Support\Facades\Storage;

it('stores, lists, reads and deletes a blob', function (ObjectStoreProviders $provider) {
    Storage::fake('s3');
    Storage::fake('public');
    $store = app(ObjectStoreService::class);

    $stored = $store->put('images/a.png', 'png-bytes', 'image/png', $provider);

    expect($stored->byteSize)->toBe(9)
        ->and($store->get('images/a.png', $provider))->toBe('png-bytes')
        ->and(iterator_to_array($store->list('images', $provider), false))->toBe([['key' => 'images/a.png', 'size' => 9]]);

    $store->delete('images/a.png', $provider);

    expect($store->exists('images/a.png', $provider))->toBeFalse()
        ->and(fn () => $store->get('images/a.png', $provider))->toThrow(ObjectNotFound::class);
})->with(ObjectStoreProviders::cases());
