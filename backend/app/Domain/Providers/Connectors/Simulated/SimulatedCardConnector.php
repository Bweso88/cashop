<?php

namespace App\Domain\Providers\Connectors\Simulated;

use App\Domain\Providers\Connectors\Contracts\WalletConnector;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ConnectorStatus;

/**
 * Paiement par carte Visa SIMULÉ (Visa Direct : NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED).
 *
 * Le client saisit sa carte sur une page de paiement hébergée par le prestataire (ici une page de
 * démonstration) : Cashop ne reçoit jamais le numéro de carte ni le cryptogramme. Cashop ne connaît
 * que le résultat et les 4 derniers chiffres renvoyés par le prestataire.
 */
class SimulatedCardConnector extends SimulatedConnector implements WalletConnector
{
    public const OUTCOMES = ['APPROVED', 'DECLINED', 'AUTHENTICATION_FAILED'];

    public function capabilities(): array
    {
        return [ConnectorCapability::Collect, ConnectorCapability::Refund, ConnectorCapability::Status, ConnectorCapability::Reconcile];
    }

    public function collect(CollectionRequest $request): ConnectorResult
    {
        if (! $this->store->get($this->code, $request->reference)) {
            $this->store->put($this->code, $request->reference, [
                'type' => 'card_payment',
                'status' => 'AWAITING_CUSTOMER',
                'amount' => $request->amount,
                'currency' => $request->currency,
                'description' => $request->description,
                'created_at' => now()->getTimestamp(),
            ]);
        }

        return $this->collectionStatus($request->reference);
    }

    /** DÉMONSTRATION : résultat choisi par le client sur la page de paiement simulée. */
    public function completeSimulatedPayment(string $reference, string $outcome): void
    {
        $record = $this->store->get($this->code, $reference)
            ?? throw new ConnectorException('Session de paiement inconnue (simulation).', definitive: true, failureCode: 'NOT_FOUND');
        if ($record['status'] !== 'AWAITING_CUSTOMER' || ! in_array($outcome, self::OUTCOMES, true)) {
            return; // déjà conclue : le résultat ne change plus
        }
        $record['status'] = $outcome;
        $record['card_masked'] = '•••• 4242';
        $record['provider_reference'] = $this->simulatedReference('VISA');
        $this->store->put($this->code, $reference, $record);
    }

    public function session(string $reference): ?array
    {
        return $this->store->get($this->code, $reference);
    }

    public function collectionStatus(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucun paiement par carte avec cette référence (simulation).');
        }

        return match ($record['status']) {
            'APPROVED' => new ConnectorResult(ConnectorStatus::Confirmed, $record['provider_reference'], raw: ['card_masked' => $record['card_masked']]),
            'DECLINED' => new ConnectorResult(ConnectorStatus::Failed, $record['provider_reference'], failureCode: 'CARD_DECLINED', failureReason: 'Paiement refusé par la banque de la carte (simulation).'),
            'AUTHENTICATION_FAILED' => new ConnectorResult(ConnectorStatus::Failed, $record['provider_reference'], failureCode: 'AUTHENTICATION_FAILED', failureReason: 'Authentification 3-D Secure échouée (simulation).'),
            default => new ConnectorResult(ConnectorStatus::Pending),
        };
    }

    /** Remboursement sur la carte d'origine. */
    public function disburse(DisbursementRequest $request): ConnectorResult
    {
        if (! $this->store->get($this->code, $request->reference)) {
            $this->store->put($this->code, $request->reference, [
                'type' => 'card_refund',
                'created_at' => now()->getTimestamp(),
                'provider_reference' => $this->simulatedReference('VISA-RF'),
            ]);
        }

        return $this->disbursementStatus($request->reference);
    }

    public function disbursementStatus(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucun remboursement avec cette référence (simulation).');
        }

        return $this->elapsed($record) < $this->delaySeconds
            ? new ConnectorResult(ConnectorStatus::Pending, $record['provider_reference'])
            : new ConnectorResult(ConnectorStatus::Confirmed, $record['provider_reference']);
    }
}
