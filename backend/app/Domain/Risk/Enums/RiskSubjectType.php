<?php

namespace App\Domain\Risk\Enums;

use App\Support\Enums\HasValues;

enum RiskSubjectType: string
{
    use HasValues;

    case User = 'USER';
    case Transfer = 'TRANSFER';
}
