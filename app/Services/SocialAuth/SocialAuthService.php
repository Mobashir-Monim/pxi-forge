<?php

namespace App\Services\SocialAuth;

use App\Services\Shared\ProviderRegistry;
use App\Services\SocialAuth\Contracts\IdentityProvider;
use App\Services\SocialAuth\Data\SocialUser;
use App\Services\SocialAuth\Enums\SocialAuthProviders;

/** SocialAuth service root. The user picks the provider, so every call names one. */
final class SocialAuthService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function redirectUrl(SocialAuthProviders $provider, string $state): string
    {
        return $this->provider($provider)->redirectUrl($state);
    }

    public function user(SocialAuthProviders $provider, string $code): SocialUser
    {
        return $this->provider($provider)->user($code);
    }

    private function provider(SocialAuthProviders $provider): IdentityProvider
    {
        /** @var IdentityProvider */
        return $this->registry->resolve('socialAuth', $provider->value);
    }
}
