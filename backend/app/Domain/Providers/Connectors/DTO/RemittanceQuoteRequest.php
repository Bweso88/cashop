<?php

namespace App\Domain\Providers\Connectors\DTO;

use App\Domain\Payments\Enums\PayoutMethod;

final readonly class RemittanceQuoteRequest
{
    public function __construct(
        public string $originCountry,
        public string $destinationCountry,
        public PayoutMethod $payoutMethod,
        public int $sendAmount,
        public string $sendCurrency,
        public string $receiveCurrency,
    ) {}
}
