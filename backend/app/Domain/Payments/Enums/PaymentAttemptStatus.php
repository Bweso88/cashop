<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

enum PaymentAttemptStatus: string
{
    use HasValues;

    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
    /** Résultat inconnu (délai, erreur réseau) : à vérifier auprès du prestataire avant toute nouvelle tentative. */
    case Unknown = 'UNKNOWN';
}
