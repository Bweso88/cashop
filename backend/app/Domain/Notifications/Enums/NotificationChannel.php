<?php

namespace App\Domain\Notifications\Enums;

use App\Support\Enums\HasValues;

enum NotificationChannel: string
{
    use HasValues;

    case Push = 'PUSH';
    case Sms = 'SMS';
    case Email = 'EMAIL';
    case InApp = 'IN_APP';
}
