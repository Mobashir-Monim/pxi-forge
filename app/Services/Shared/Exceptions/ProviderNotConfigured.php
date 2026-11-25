<?php

namespace App\Services\Shared\Exceptions;

/** A service was asked for a provider that has no adapter mapped, or is missing its credentials. */
final class ProviderNotConfigured extends ProviderException
{
    public static function for(string $service, string $provider): self
    {
        return new self("No adapter mapped for provider [{$provider}] in service [{$service}]. Add it to config/services-arch.php.");
    }
}
