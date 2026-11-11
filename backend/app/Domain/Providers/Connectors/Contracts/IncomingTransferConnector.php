<?php

namespace App\Domain\Providers\Connectors\Contracts;

use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\IncomingTransfer;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;

/**
 * Réception d'un transfert envoyé depuis l'étranger. Le RÉSEAU verse les fonds sur le portefeuille
 * Mobile Money du bénéficiaire : Cashop ne reçoit ni ne détient l'argent (ADR 0004).
 *
 * Réseaux réels : NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED (ex. MoneyGram Payout API).
 */
interface IncomingTransferConnector extends Connector
{
    /** @throws ConnectorException NOT_FOUND (définitif) si la référence est inconnue du réseau. */
    public function lookupIncoming(string $reference): IncomingTransfer;

    /** Demande de versement sur un portefeuille. Idempotent sur la référence du transfert. */
    public function requestIncomingPayout(string $reference, string $walletProvider, string $msisdn): ConnectorResult;

    public function incomingPayoutStatus(string $reference): ConnectorResult;
}
