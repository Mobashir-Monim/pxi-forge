<?php

namespace App\Services\SocialAuth\Contracts;

use App\Services\SocialAuth\Data\SocialUser;

/** OAuth sign-in with an outside identity provider. */
interface IdentityProvider
{
    /** Where to send the user to sign in; $state comes back on the callback and must be checked against the session. */
    public function redirectUrl(string $state): string;

    /** Trade the callback's code for the signed-in identity. */
    public function user(string $code): SocialUser;
}
