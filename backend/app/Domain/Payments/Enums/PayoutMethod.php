<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum PayoutMethod: string
{
    use HasValues;

    case Card = 'card';
    case MobileMoney = 'mobile_money';
    case BankAccount = 'bank_account';
    case CashPickup = 'cash_pickup';
}
