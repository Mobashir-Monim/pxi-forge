<?php

namespace App\Services\ObjectStore\Providers;

use App\Services\ObjectStore\Contracts\ObjectStore;
use App\Services\ObjectStore\Data\StoredObject;
use App\Services\ObjectStore\Exceptions\ObjectNotFound;

/** In-memory object store for tests and local dev, no network. */
final class FakeObjectStore implements ObjectStore
{
    /**
     * @var array<string, array{contents: string, mime: ?string}>
     */
    private static array $store = [];

    /** Makes writes and deletes fail, so a caller's "the object was not stored" or "not destroyed" branch is reachable in a test. */
    public static bool $failWrites = false;

    /** Makes only writes to this key fail, so a caller that writes many objects can be stopped part way while its deletes still work. */
    public static ?string $failWriteTo = null;

    /** Makes reads of this key fail although it exists, so a caller's "the store is down" branch is reachable after it wrote. */
    public static ?string $failReadOf = null;

    public static function flush(): void
    {
        self::$store = [];
        self::$failWrites = false;
        self::$failWriteTo = null;
        self::$failReadOf = null;
    }

    public function put(string $key, string $contents, ?string $mime = null): StoredObject
    {
        if (self::$failWrites || $key === self::$failWriteTo) {
            throw new \RuntimeException("fake object store: write failed for [{$key}].");
        }

        self::$store[$key] = ['contents' => $contents, 'mime' => $mime];

        return new StoredObject(
            key: $key,
            url: $this->url($key),
            byteSize: strlen($contents),
            mime: $mime,
        );
    }

    public function get(string $key): string
    {
        if ($key === self::$failReadOf) {
            throw new \RuntimeException("fake object store: read failed for [{$key}].");
        }

        return self::$store[$key]['contents'] ?? throw ObjectNotFound::forKey($key);
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, self::$store);
    }

    /**
     * @return iterable<array{key: string, size: int}>
     */
    public function list(string $prefix): iterable
    {
        foreach (self::$store as $key => $object) {
            if (str_starts_with($key, $prefix)) {
                yield ['key' => $key, 'size' => strlen($object['contents'])];
            }
        }
    }

    public function url(string $key): string
    {
        return 'https://objectstore.fake/'.ltrim($key, '/');
    }

    public function delete(string $key): void
    {
        if (self::$failWrites) {
            throw new \RuntimeException("fake object store: delete failed for [{$key}].");
        }

        unset(self::$store[$key]);
    }
}
