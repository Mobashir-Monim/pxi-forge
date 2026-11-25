<?php

namespace App\Services\ObjectStore\Exceptions;

use App\Services\Shared\Exceptions\ProviderException;

/** A blob was requested by a key the store has no object for. */
final class ObjectNotFound extends ProviderException
{
    public static function forKey(string $key): self
    {
        return new self("No stored object for key [{$key}].");
    }
}
