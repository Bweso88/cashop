<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum KycStatus: string
{
    use HasValues;

    case Pending = 'PENDING';
    case InReview = 'IN_REVIEW';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
}
