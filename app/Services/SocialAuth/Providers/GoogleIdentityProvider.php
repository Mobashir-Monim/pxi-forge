<?php

namespace App\Services\SocialAuth\Providers;

use App\Services\Shared\Exceptions\ProviderNotConfigured;
use App\Services\SocialAuth\Contracts\IdentityProvider;
use App\Services\SocialAuth\Data\SocialUser;

/** ponytail: not built yet. The seam is in place; wire Google OAuth (or Socialite) here when social sign-in is picked up. */
final class GoogleIdentityProvider implements IdentityProvider
{
    public function redirectUrl(string $state): string
    {
        throw new ProviderNotConfigured('Google sign-in is not available yet.');
    }

    public function user(string $code): SocialUser
    {
        throw new ProviderNotConfigured('Google sign-in is not available yet.');
    }
}
