<?php

namespace App\Domain\Webhooks\Enums;

use App\Support\Enums\HasValues;

enum WebhookStatus: string
{
    use HasValues;

    case Received = 'RECEIVED';
    case Processed = 'PROCESSED';
    case Ignored = 'IGNORED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
}
