<?php

namespace App\Services\SocialAuth\Enums;

enum SocialAuthProviders: string
{
    case Google = 'google';
    case GitHub = 'github';
}
