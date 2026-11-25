<?php

namespace App\Services\ObjectStore\Enums;

enum ObjectStoreProviders: string
{
    case Fake = 'fake';
    case S3 = 's3';
    case Local = 'local';
}
