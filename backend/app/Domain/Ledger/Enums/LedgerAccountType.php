<?php

namespace App\Domain\Ledger\Enums;

use App\Support\Enums\HasValues;

enum LedgerAccountType: string
{
    use HasValues;

    case Asset = 'ASSET';
    case Liability = 'LIABILITY';
    case Revenue = 'REVENUE';
    case Expense = 'EXPENSE';
    case Equity = 'EQUITY';
}
