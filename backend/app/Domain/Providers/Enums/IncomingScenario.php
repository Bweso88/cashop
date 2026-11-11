<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

/** Scénarios des transferts entrants simulés (environnement « mock » uniquement). */
enum IncomingScenario: string
{
    use HasValues;

    case Success = 'SUCCESS';
    case PayoutFailed = 'PAYOUT_FAILED';
    case PayoutPending = 'PAYOUT_PENDING';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Versement réussi',
            self::PayoutFailed => 'Versement refusé par le réseau',
            self::PayoutPending => 'Versement en attente prolongée',
        };
    }
}
