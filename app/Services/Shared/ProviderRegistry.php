<?php

namespace App\Services\Shared;

use App\Services\Shared\Exceptions\ProviderNotConfigured;
use Illuminate\Contracts\Container\Container;

/** Resolves a service's provider adapter from the config/services-arch.php map. */
final class ProviderRegistry
{
    /**
     * @param  array<string, array{default: string, providers: array<string, class-string>}>  $config
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $config,
    ) {}

    /**
     * @param  string  $service  capability key, e.g. 'payment'
     * @param  string|null  $provider  provider key (enum value), null for the configured default
     */
    public function resolve(string $service, ?string $provider = null): object
    {
        $entry = $this->config[$service]
            ?? throw new ProviderNotConfigured("Unknown service [{$service}] in config/services-arch.php.");

        $key = $provider ?? $entry['default'];

        $class = $entry['providers'][$key]
            ?? throw ProviderNotConfigured::for($service, $key);

        // A fake reports success and does nothing, so outside local and testing it fails loudly instead.
        if ($key === 'fake' && ! app()->environment(['local', 'testing'])) {
            $env = app()->environment();

            throw new ProviderNotConfigured("The fake {$service} provider cannot run in {$env}. Set its provider in config/services-arch.php.");
        }

        return $this->container->make($class);
    }
}
