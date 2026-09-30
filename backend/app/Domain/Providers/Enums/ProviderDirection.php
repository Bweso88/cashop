<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ProviderDirection: string
{
    use HasValues;

    case Send = 'SEND';
    case Receive = 'RECEIVE';
    case Both = 'BOTH';
}
