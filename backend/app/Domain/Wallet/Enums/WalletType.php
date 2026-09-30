<?php

namespace App\Domain\Wallet\Enums;

use App\Support\Enums\HasValues;

enum WalletType: string
{
    use HasValues;

    case Personal = 'PERSONAL';
    case Shared = 'SHARED';
    case Savings = 'SAVINGS';
}
