<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ProviderOperation: string
{
    use HasValues;

    case Quote = 'QUOTE';
    case Create = 'CREATE';
    case Commit = 'COMMIT';
    case Status = 'STATUS';
    case Cancel = 'CANCEL';
    case Refund = 'REFUND';
    case Collect = 'COLLECT';
    case CollectStatus = 'COLLECT_STATUS';
    case Disburse = 'DISBURSE';
    case DisburseStatus = 'DISBURSE_STATUS';
    case Health = 'HEALTH';
}
