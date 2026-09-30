<?php

namespace App\Domain\Risk\Enums;

use App\Support\Enums\HasValues;

enum ComplianceCaseStatus: string
{
    use HasValues;

    case Open = 'OPEN';
    case InReview = 'IN_REVIEW';
    case Escalated = 'ESCALATED';
    case ClosedNoAction = 'CLOSED_NO_ACTION';
    case ClosedReported = 'CLOSED_REPORTED';
}
