<?php

namespace App\Domain\Providers\Connectors\Contracts;

use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\FieldSpec;
use App\Domain\Providers\Connectors\DTO\RemittanceOrder;
use App\Domain\Providers\Connectors\DTO\RemittanceQuote;
use App\Domain\Providers\Connectors\DTO\RemittanceQuoteRequest;

/** Réseau de transfert international (Western Union, MoneyGram, Ria…). */
interface RemittanceConnector extends Connector
{
    public function quote(RemittanceQuoteRequest $request): RemittanceQuote;

    /** @return list<FieldSpec> Champs bénéficiaire demandés pour ce corridor. */
    public function requiredFields(string $destinationCountry, PayoutMethod $method): array;

    /** @return array<string, string> Erreurs de validation par champ (vide si valide). */
    public function validate(RemittanceOrder $order): array;

    /** Crée le transfert. Idempotent sur $order->reference. */
    public function createTransfer(RemittanceOrder $order): ConnectorResult;

    /** Statut, interrogé avec la référence Cashop (permet de retrouver un transfert créé malgré un délai). */
    public function transferStatus(string $reference): ConnectorResult;

    public function cancel(string $reference): ConnectorResult;
}
