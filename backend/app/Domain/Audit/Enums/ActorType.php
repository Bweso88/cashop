<?php

namespace App\Domain\Audit\Enums;

use App\Support\Enums\HasValues;

enum ActorType: string
{
    use HasValues;

    case User = 'USER';
    case Admin = 'ADMIN';
    case System = 'SYSTEM';
    case Provider = 'PROVIDER';
}
