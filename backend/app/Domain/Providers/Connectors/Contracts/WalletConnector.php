<?php

namespace App\Domain\Providers\Connectors\Contracts;

use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;

/** Portefeuille Mobile Money (MTN MoMo, Airtel Money…). */
interface WalletConnector extends Connector
{
    /** Demande de débit : le client valide sur son téléphone. Renvoie en général PENDING. */
    public function collect(CollectionRequest $request): ConnectorResult;

    public function collectionStatus(string $reference): ConnectorResult;

    /** Crédit d'un portefeuille (remboursement, versement d'un transfert reçu). */
    public function disburse(DisbursementRequest $request): ConnectorResult;

    public function disbursementStatus(string $reference): ConnectorResult;
}
