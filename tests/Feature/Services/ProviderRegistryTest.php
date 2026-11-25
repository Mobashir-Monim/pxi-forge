<?php

use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\Shared\ProviderRegistry;

function registry(): ProviderRegistry
{
    return new ProviderRegistry(app(), [
        'demo' => ['default' => 'fake', 'providers' => ['fake' => ArrayObject::class, 'real' => SplStack::class]],
    ]);
}

it('resolves the default provider and an explicit one', function () {
    expect(registry()->resolve('demo'))->toBeInstanceOf(ArrayObject::class)
        ->and(registry()->resolve('demo', 'real'))->toBeInstanceOf(SplStack::class);
});

it('refuses an unknown service or provider', function (string $service, ?string $provider) {
    registry()->resolve($service, $provider);
})->throws(ProviderNotConfigured::class)->with([
    'service' => ['nope', null],
    'provider' => ['demo', 'nope'],
]);

it('refuses a fake provider outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    registry()->resolve('demo');
})->throws(ProviderNotConfigured::class, 'cannot run in production');

it('answers 404 to a webhook for an unmapped service', function () {
    $this->post('/webhooks/nope/fake')->assertNotFound();
});
