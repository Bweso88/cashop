<?php

namespace App\Domain\Providers\Connectors\DTO;

use App\Domain\Providers\Enums\SimulationScenario;

/**
 * Demande de débit d'un portefeuille Mobile Money. Le client autorise le paiement sur son
 * téléphone, selon le mécanisme de l'opérateur : Cashop ne demande jamais son PIN Mobile Money.
 */
final readonly class CollectionRequest
{
    public function __construct(
        public string $reference,
        public string $msisdn,
        public int $amount,
        public string $currency,
        public string $description,
        public ?SimulationScenario $scenario = null,
    ) {}
}
