<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case Wallet = 'wallet';
    case Card = 'card';
    case MobileMoney = 'mobile_money';
    case BankAccount = 'bank_account';
}
