<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

/** Réception d'un transfert : le réseau verse sur le portefeuille Mobile Money du bénéficiaire. */
enum IncomingTransferStatus: string
{
    use HasValues;

    case PayoutPending = 'PAYOUT_PENDING';
    case PaidOut = 'PAID_OUT';
    case Failed = 'FAILED';
    case RequiresReconciliation = 'REQUIRES_RECONCILIATION';

    public function label(): string
    {
        return match ($this) {
            self::PayoutPending => 'Versement en cours',
            self::PaidOut => 'Versé sur votre Mobile Money',
            self::Failed => 'Versement refusé',
            self::RequiresReconciliation => 'Vérification en cours',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PaidOut, self::Failed], true);
    }
}
