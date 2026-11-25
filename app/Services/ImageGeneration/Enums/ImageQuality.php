<?php

namespace App\Services\ImageGeneration\Enums;

enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
