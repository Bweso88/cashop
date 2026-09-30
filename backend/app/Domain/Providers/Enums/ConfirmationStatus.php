<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

enum ConfirmationStatus: string
{
    use HasValues;

    case Confirmed = 'CONFIRMED';
    case NotConfirmed = 'NOT_CONFIRMED';
}
