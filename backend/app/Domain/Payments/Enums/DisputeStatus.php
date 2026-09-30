<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum DisputeStatus: string
{
    use HasValues;

    case Open = 'OPEN';
    case Investigating = 'INVESTIGATING';
    case ResolvedCustomer = 'RESOLVED_CUSTOMER';
    case ResolvedMerchant = 'RESOLVED_MERCHANT';
    case Closed = 'CLOSED';
}
