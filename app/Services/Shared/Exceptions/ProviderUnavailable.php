<?php

namespace App\Services\Shared\Exceptions;

use Throwable;

/** Provider could not be reached or is down (timeout, 5xx, connection refused). Safe to retry. */
final class ProviderUnavailable extends ProviderException
{
    public function __construct(
        string $message = '',
        ?Throwable $previous = null,
        // Seconds the provider asked us to wait (Retry-After), when it said.
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
