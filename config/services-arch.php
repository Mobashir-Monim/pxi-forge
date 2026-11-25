<?php

use App\Services\ImageGeneration\Providers\FakeImageGenerator;
use App\Services\ImageGeneration\Providers\PixelforgeImageGenerator;

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

];
