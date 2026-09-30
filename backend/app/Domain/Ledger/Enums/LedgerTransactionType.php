<?php

namespace App\Domain\Ledger\Enums;

use App\Support\Enums\HasValues;

enum LedgerTransactionType: string
{
    use HasValues;

    case TransferReserve = 'TRANSFER_RESERVE';
    case TransferSettle = 'TRANSFER_SETTLE';
    case TransferReverse = 'TRANSFER_REVERSE';
    case Deposit = 'DEPOSIT';
    case Refund = 'REFUND';
    case FxConversion = 'FX_CONVERSION';
    case Adjustment = 'ADJUSTMENT';
}
