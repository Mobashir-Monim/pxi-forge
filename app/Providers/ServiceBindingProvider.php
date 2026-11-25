<?php

namespace App\Providers;

use App\Services\Shared\ProviderRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/** Wires the external-service layer. */
final class ServiceBindingProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, fn (Container $app): ProviderRegistry => new ProviderRegistry($app, config('services-arch')));
    }
}
