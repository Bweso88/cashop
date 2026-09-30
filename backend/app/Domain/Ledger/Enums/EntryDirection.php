<?php

namespace App\Domain\Ledger\Enums;

use App\Support\Enums\HasValues;

enum EntryDirection: string
{
    use HasValues;

    case Debit = 'DEBIT';
    case Credit = 'CREDIT';
}
