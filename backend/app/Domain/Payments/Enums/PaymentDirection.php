<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

/** Sens d'un mouvement sur un portefeuille Mobile Money. */
enum PaymentDirection: string
{
    use HasValues;

    /** Débit du portefeuille du client (financement d'un transfert). */
    case Collection = 'COLLECTION';
    /** Crédit d'un portefeuille (remboursement, versement d'un transfert reçu). */
    case Disbursement = 'DISBURSEMENT';
}
