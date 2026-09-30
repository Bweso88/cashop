<?php

namespace App\Domain\Wallet\Enums;

use App\Support\Enums\HasValues;

enum WalletTransactionType: string
{
    use HasValues;

    case Deposit = 'DEPOSIT';
    case Withdrawal = 'WITHDRAWAL';
    case TransferOut = 'TRANSFER_OUT';
    case TransferIn = 'TRANSFER_IN';
    case Fee = 'FEE';
    case Refund = 'REFUND';
    case Reversal = 'REVERSAL';
}
