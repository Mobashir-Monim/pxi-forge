<?php

namespace App\Services\ObjectStore\Data;

/** App-shaped handle to a durable blob. */
final class StoredObject
{
    public function __construct(
        public readonly string $key,
        public readonly string $url,
        public readonly int $byteSize,
        public readonly ?string $mime,
    ) {}
}
