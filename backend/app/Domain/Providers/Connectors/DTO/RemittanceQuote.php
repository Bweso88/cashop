<?php

namespace App\Domain\Providers\Connectors\DTO;

use DateTimeInterface;

final readonly class RemittanceQuote
{
    public function __construct(
        /** Frais du réseau, dans la devise d'envoi (unités mineures). */
        public int $providerFee,
        /** Taux proposé par le réseau (décimal en chaîne) ou null : Cashop applique alors son taux. */
        public ?string $fxRate,
        public DateTimeInterface $expiresAt,
        public ?string $providerQuoteReference,
        public bool $isSimulated,
    ) {}
}
