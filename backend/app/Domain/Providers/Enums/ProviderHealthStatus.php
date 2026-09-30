<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ProviderHealthStatus: string
{
    use HasValues;

    case Connected = 'CONNECTED';
    case Degraded = 'DEGRADED';
    case Offline = 'OFFLINE';
    case Unknown = 'UNKNOWN';
}
