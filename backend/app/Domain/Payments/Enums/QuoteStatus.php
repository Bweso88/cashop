<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum QuoteStatus: string
{
    use HasValues;

    case Active = 'ACTIVE';
    case Used = 'USED';
    case Expired = 'EXPIRED';
}
