<?php

use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\SocialAuth\Enums\SocialAuthProviders;
use App\Services\SocialAuth\SocialAuthService;

it('refuses social sign-in until the adapters are built', function (SocialAuthProviders $provider) {
    expect(fn () => app(SocialAuthService::class)->redirectUrl($provider, 'state'))->toThrow(ProviderNotConfigured::class)
        ->and(fn () => app(SocialAuthService::class)->user($provider, 'code'))->toThrow(ProviderNotConfigured::class);
})->with(SocialAuthProviders::cases());
