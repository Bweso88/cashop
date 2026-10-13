<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

/** Statut normalisé renvoyé par un connecteur, quel que soit le vocabulaire du prestataire. */
enum ConnectorStatus: string
{
    use HasValues;

    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case AvailableForPickup = 'AVAILABLE_FOR_PICKUP';
    case PaidOut = 'PAID_OUT';
    case Cancelled = 'CANCELLED';
    case Failed = 'FAILED';
    /** Réponse non interprétable : jamais traitée comme un succès. */
    case Unknown = 'UNKNOWN';
}
