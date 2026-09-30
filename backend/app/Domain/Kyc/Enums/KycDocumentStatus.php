<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum KycDocumentStatus: string
{
    use HasValues;

    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
}
