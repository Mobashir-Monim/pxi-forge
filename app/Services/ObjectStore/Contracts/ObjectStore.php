<?php

namespace App\Services\ObjectStore\Contracts;

use App\Services\ObjectStore\Data\StoredObject;
use App\Services\ObjectStore\Exceptions\ObjectNotFound;

/** Durable blob storage in app terms. */
interface ObjectStore
{
    public function put(string $key, string $contents, ?string $mime = null): StoredObject;

    /**
     * @throws ObjectNotFound
     */
    public function get(string $key): string;

    public function exists(string $key): bool;

    /**
     * @return iterable<array{key: string, size: int}>
     */
    public function list(string $prefix): iterable;

    /** Durable read URL. */
    public function url(string $key): string;

    public function delete(string $key): void;
}
