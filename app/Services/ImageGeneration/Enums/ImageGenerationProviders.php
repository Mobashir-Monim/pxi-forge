<?php

namespace App\Services\ImageGeneration\Enums;

enum ImageGenerationProviders: string
{
    case Fake = 'fake';
    case Pixelforge = 'pixelforge';
}
