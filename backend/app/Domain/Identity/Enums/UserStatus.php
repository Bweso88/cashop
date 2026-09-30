<?php

namespace App\Domain\Identity\Enums;

use App\Support\Enums\HasValues;

enum UserStatus: string
{
    use HasValues;

    case Active = 'ACTIVE';
    case Locked = 'LOCKED';
    case Suspended = 'SUSPENDED';
    case Closed = 'CLOSED';
}
