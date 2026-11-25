<?php

namespace App\Services\Shared\Exceptions;

use Throwable;

/** Provider rejected the call for rate limiting (429). */
final class ProviderRateLimited extends ProviderException
{
    public function __construct(
        string $message = '',
        // Seconds the provider asked us to wait (Retry-After), when it said.
        public readonly ?int $retryAfter = null,
        // The provider's own error code, e.g. a daily quota that no short wait will clear.
        public readonly ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
