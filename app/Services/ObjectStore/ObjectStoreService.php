<?php

namespace App\Services\ObjectStore;

use App\Services\ObjectStore\Contracts\ObjectStore;
use App\Services\ObjectStore\Data\StoredObject;
use App\Services\ObjectStore\Enums\ObjectStoreProviders;
use App\Services\Shared\ProviderRegistry;

/** ObjectStore service root, the public API for durable blobs. */
final class ObjectStoreService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function put(string $key, string $contents, ?string $mime = null, ?ObjectStoreProviders $using = null): StoredObject
    {
        return $this->provider($using)->put($key, $contents, $mime);
    }

    public function get(string $key, ?ObjectStoreProviders $using = null): string
    {
        return $this->provider($using)->get($key);
    }

    public function exists(string $key, ?ObjectStoreProviders $using = null): bool
    {
        return $this->provider($using)->exists($key);
    }

    /**
     * @return iterable<array{key: string, size: int}>
     */
    public function list(string $prefix, ?ObjectStoreProviders $using = null): iterable
    {
        return $this->provider($using)->list($prefix);
    }

    public function url(string $key, ?ObjectStoreProviders $using = null): string
    {
        return $this->provider($using)->url($key);
    }

    public function delete(string $key, ?ObjectStoreProviders $using = null): void
    {
        $this->provider($using)->delete($key);
    }

    private function provider(?ObjectStoreProviders $using = null): ObjectStore
    {
        /**
         * @var ObjectStore
         */
        return $this->registry->resolve('objectStore', $using?->value);
    }
}
