<?php

namespace App\Domain\Providers\Connectors\DTO;

use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Providers\Enums\SimulationScenario;

/** Ordre de transfert transmis au réseau, une fois le financement confirmé. */
final readonly class RemittanceOrder
{
    public function __construct(
        /** Référence Cashop : sert de clé d'idempotence auprès du réseau. */
        public string $reference,
        public string $destinationCountry,
        public PayoutMethod $payoutMethod,
        public int $sendAmount,
        public string $sendCurrency,
        public int $receiveAmount,
        public string $receiveCurrency,
        public array $sender,
        public array $beneficiary,
        public ?string $providerQuoteReference = null,
        public ?SimulationScenario $scenario = null,
    ) {}
}
