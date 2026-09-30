<?php

namespace App\Domain\Notifications\Enums;

use App\Support\Enums\HasValues;

enum NotificationStatus: string
{
    use HasValues;

    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Read = 'READ';
}
