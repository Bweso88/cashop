<?php

namespace App\Domain\Identity\Enums;

use App\Support\Enums\HasValues;

enum OtpChannel: string
{
    use HasValues;

    case Sms = 'sms';
    case Email = 'email';
}
