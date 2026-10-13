<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

/**
 * Scénarios des connecteurs simulés (environnement « mock » uniquement). Ils permettent de
 * démontrer et de tester chaque cas, réussite comme échec, sans aucun mouvement de fonds réel.
 */
enum SimulationScenario: string
{
    use HasValues;

    case Success = 'SUCCESS';
    case PaymentRefused = 'PAYMENT_REFUSED';
    case PaymentTimeout = 'PAYMENT_TIMEOUT';
    case PaymentUnknownResponse = 'PAYMENT_UNKNOWN_RESPONSE';
    case TransferFailed = 'TRANSFER_FAILED';
    case TransferPending = 'TRANSFER_PENDING';
    case TransferTimeout = 'TRANSFER_TIMEOUT';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Réussite',
            self::PaymentRefused => 'Paiement Mobile Money refusé',
            self::PaymentTimeout => 'Paiement sans réponse (délai dépassé)',
            self::PaymentUnknownResponse => 'Réponse opérateur inconnue',
            self::TransferFailed => 'Paiement confirmé, transfert refusé par le réseau',
            self::TransferPending => 'Transfert en attente prolongée',
            self::TransferTimeout => 'Délai dépassé à la création du transfert',
        };
    }
}
