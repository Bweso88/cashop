<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum KycCheckResult: string
{
    use HasValues;

    case Pass = 'PASS';
    case Fail = 'FAIL';
    case Review = 'REVIEW';
}
