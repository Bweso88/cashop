<?php

namespace App\Domain\Kyc\Enums;

use App\Support\Enums\HasValues;

enum KycDocumentType: string
{
    use HasValues;

    case NationalId = 'NATIONAL_ID';
    case Passport = 'PASSPORT';
    case DriverLicense = 'DRIVER_LICENSE';
    case ProofOfAddress = 'PROOF_OF_ADDRESS';
    case Selfie = 'SELFIE';
}
