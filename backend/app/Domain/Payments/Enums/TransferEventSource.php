<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum TransferEventSource: string
{
    use HasValues;

    case Api = 'API';
    case Webhook = 'WEBHOOK';
    case Polling = 'POLLING';
    case Admin = 'ADMIN';
    case System = 'SYSTEM';
}
