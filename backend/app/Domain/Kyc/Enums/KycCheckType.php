<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum KycCheckType: string
{
    use HasValues;

    case Document = 'DOCUMENT';
    case Liveness = 'LIVENESS';
    case FaceMatch = 'FACE_MATCH';
    case Sanctions = 'SANCTIONS';
    case Pep = 'PEP';
}
