<?php

namespace App\Services\SocialAuth\Data;

/** The identity a provider vouched for after the OAuth round trip. */
final class SocialUser
{
    public function __construct(
        // The provider's stable user id; link accounts on this, not on email.
        public readonly string $id,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $avatarUrl,
    ) {}
}
