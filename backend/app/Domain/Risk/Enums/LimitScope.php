<?php

namespace App\Domain\Risk\Enums;

use App\Support\Enums\HasValues;

enum LimitScope: string
{
    use HasValues;

    case PerTransaction = 'PER_TRANSACTION';
    case Daily = 'DAILY';
    case Monthly = 'MONTHLY';
}
