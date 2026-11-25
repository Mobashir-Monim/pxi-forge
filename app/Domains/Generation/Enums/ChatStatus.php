<?php

namespace App\Domains\Generation\Enums;

enum ChatStatus: string
{
    case Active = 'active';

    /** Read-only for good, e.g. after Pixelforge blocked a prompt in it. */
    case Archived = 'archived';
}
