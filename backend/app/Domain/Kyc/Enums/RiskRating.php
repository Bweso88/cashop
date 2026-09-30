<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum RiskRating: string
{
    use HasValues;

    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
}
