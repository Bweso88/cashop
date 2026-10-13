<?php

namespace App\Domain\Providers\Connectors\DTO;

use App\Domain\Providers\Enums\ConnectorStatus;

final readonly class ConnectorResult
{
    public function __construct(
        public ConnectorStatus $status,
        public ?string $providerReference = null,
        public ?string $pickupCode = null,
        public ?string $failureCode = null,
        public ?string $failureReason = null,
        /** Réponse brute masquée (jamais de PIN, de secret ni de numéro complet). */
        public array $raw = [],
    ) {}
}
