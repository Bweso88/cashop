<?php

namespace App\Domain\Wallet\Enums;

use App\Support\Enums\HasValues;

enum WalletStatus: string
{
    use HasValues;

    case Active = 'ACTIVE';
    case Frozen = 'FROZEN';
    case Closed = 'CLOSED';
}
