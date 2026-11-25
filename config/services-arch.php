<?php

use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use App\Services\ImageGeneration\Providers\PixelforgeImageGenerator;
use App\Services\Payment\Providers\FakePaymentGateway;
use App\Services\Payment\Providers\StripePaymentGateway;
use App\Services\SocialAuth\Providers\GitHubIdentityProvider;
use App\Services\SocialAuth\Providers\GoogleIdentityProvider;

/*
 * External services: each capability maps a default provider key to its adapter class.
 * App\Services\Shared\ProviderRegistry resolves from this map, and `fake` only runs in local and testing.
 */
return [

    // Image generation, the core of the app.
    'imageGeneration' => [
        'default' => env('IMAGE_GENERATION_PROVIDER', 'pixelforge'),
        'providers' => [
            'fake' => FakeImageGenerator::class,
            'pixelforge' => PixelforgeImageGenerator::class,
        ],
    ],

    // Buying credits. Stripe in test mode, so purchases go through the sandbox and never move real money.
    'payment' => [
        'default' => env('PAYMENT_PROVIDER', 'stripe'),
        'providers' => [
            'fake' => FakePaymentGateway::class,
            'stripe' => StripePaymentGateway::class,
        ],
    ],

    // Social sign-in. Stubs for now: every call refuses until the adapters are built. The user picks the provider, so the default is unused.
    'socialAuth' => [
        'default' => 'google',
        'providers' => [
            'google' => GoogleIdentityProvider::class,
            'github' => GitHubIdentityProvider::class,
        ],
    ],

];
