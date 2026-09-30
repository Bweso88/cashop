<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum RefundStatus: string
{
    use HasValues;

    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
}
