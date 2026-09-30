<?php

namespace App\Domain\Risk\Enums;

use App\Support\Enums\HasValues;

enum ComplianceCaseType: string
{
    use HasValues;

    case AmlAlert = 'AML_ALERT';
    case Sanctions = 'SANCTIONS';
    case KycReview = 'KYC_REVIEW';
    case Fraud = 'FRAUD';
    case Sar = 'SAR';
}
