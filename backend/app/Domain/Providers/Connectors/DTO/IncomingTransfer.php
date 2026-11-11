<?php

namespace App\Domain\Providers\Connectors\DTO;

/** Transfert reçu, tel que le réseau le décrit (montant à verser, expéditeur, bénéficiaire attendu). */
final readonly class IncomingTransfer
{
    public function __construct(
        public string $reference,
        public string $status, // AVAILABLE | PAID_OUT | CANCELLED
        public int $amount,
        public string $currency,
        public string $senderName,
        public string $senderCountry,
        public string $receiverFirstName,
        public string $receiverLastName,
        public bool $isSimulated,
    ) {}
}
