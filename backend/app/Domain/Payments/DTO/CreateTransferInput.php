<?php

namespace App\Domain\Payments\DTO;

use App\Domain\Providers\Enums\SimulationScenario;

final readonly class CreateTransferInput
{
    public function __construct(
        public string $quoteId,
        public string $beneficiaryId,
        public string $fundingMsisdn,
        public string $pin,
        public ?string $purpose = null,
        public ?string $note = null,
        public ?SimulationScenario $scenario = null,
    ) {}
}
