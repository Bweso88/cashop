<?php

namespace App\Domain\Providers\Connectors\DTO;

/** Notification vérifiée : elle déclenche une relecture du statut, jamais une écriture directe. */
final readonly class WebhookNotification
{
    public function __construct(
        public ?string $eventId,
        /** Référence Cashop concernée (tentative de paiement ou transfert). */
        public ?string $reference,
        public ?\DateTimeInterface $occurredAt,
    ) {}
}
