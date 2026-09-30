<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum IdempotencyStatus: string
{
    use HasValues;

    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
