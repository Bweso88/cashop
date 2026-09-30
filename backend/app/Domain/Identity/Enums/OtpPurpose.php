<?php

namespace App\Domain\Identity\Enums;

use App\Support\Enums\HasValues;

enum OtpPurpose: string
{
    use HasValues;

    case Login = 'login';
    case Register = 'register';
    case Transfer = 'transfer';
    case Reset = 'reset';
}
