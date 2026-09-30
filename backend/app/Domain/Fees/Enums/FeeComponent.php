<?php

namespace App\Domain\Fees\Enums;

use App\Support\Enums\HasValues;

enum FeeComponent: string
{
    use HasValues;

    case Fixed = 'FIXED';
    case Percentage = 'PERCENTAGE';
    case Provider = 'PROVIDER';
    case Fx = 'FX';
    case Country = 'COUNTRY';
    case PaymentMethod = 'PAYMENT_METHOD';
    case PayoutMethod = 'PAYOUT_METHOD';
}
