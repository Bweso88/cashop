<?php

namespace App\Domain\Providers\Connectors\DTO;

final readonly class DisbursementRequest
{
    public function __construct(
        public string $reference,
        public string $msisdn,
        public int $amount,
        public string $currency,
        public string $description,
    ) {}
}
