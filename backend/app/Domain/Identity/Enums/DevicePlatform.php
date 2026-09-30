<?php

namespace App\Domain\Identity\Enums;

use App\Support\Enums\HasValues;

enum DevicePlatform: string
{
    use HasValues;

    case Ios = 'ios';
    case Android = 'android';
    case Web = 'web';
}
