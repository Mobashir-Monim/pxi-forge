<?php

namespace App\Services\Shared\Exceptions;

use Throwable;

/** Provider understood the call and refused it on its own terms (validation, content policy, declined card). Not retryable. */
final class ProviderRejected extends ProviderException
{
    public function __construct(
        string $message = '',
        public readonly ?int $httpStatus = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** Our own credentials were refused, so nothing we ask can succeed until they are fixed. */
    public function credentialsRefused(): bool
    {
        return $this->httpStatus === 401;
    }

    public function missing(): bool
    {
        return $this->httpStatus === 404;
    }
}
