<?php

namespace App\Domain\Providers\Connectors\Simulated;

use App\Domain\Providers\Connectors\Contracts\WalletConnector;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\SimulationScenario;

/**
 * Portefeuille Mobile Money SIMULÉ (MTN MoMo, Airtel Money). Aucun fonds réel n'est débité.
 * Le paiement passe à « confirmé » après le délai configuré, comme si le client avait validé
 * sur son téléphone — sauf scénario d'échec.
 */
class SimulatedWalletConnector extends SimulatedConnector implements WalletConnector
{
    public function capabilities(): array
    {
        return [
            ConnectorCapability::Health, ConnectorCapability::Collect, ConnectorCapability::Disburse,
            ConnectorCapability::Status, ConnectorCapability::Refund, ConnectorCapability::Webhook, ConnectorCapability::Reconcile,
        ];
    }

    public function collect(CollectionRequest $request): ConnectorResult
    {
        // Idempotence : la même référence ne crée jamais une seconde demande de débit.
        if ($this->store->get($this->code, $request->reference)) {
            return $this->collectionStatus($request->reference);
        }

        $record = [
            'type' => 'collection',
            'scenario' => ($request->scenario ?? SimulationScenario::Success)->value,
            'created_at' => now()->getTimestamp(),
            'provider_reference' => $this->simulatedReference(strtoupper(explode('_', $this->code)[0])),
            'amount' => $request->amount,
        ];
        $this->store->put($this->code, $request->reference, $record);

        return $this->pending($record);
    }

    public function collectionStatus(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucune demande de paiement avec cette référence (simulation).');
        }
        if ($this->elapsed($record) < $this->delaySeconds) {
            return $this->pending($record);
        }

        return match (SimulationScenario::from($record['scenario'])) {
            SimulationScenario::PaymentRefused => new ConnectorResult(ConnectorStatus::Failed, $record['provider_reference'], failureCode: 'PAYER_REJECTED', failureReason: 'Paiement refusé par le titulaire du compte (simulation).'),
            SimulationScenario::PaymentTimeout => $this->pending($record),
            SimulationScenario::PaymentUnknownResponse => new ConnectorResult(ConnectorStatus::Unknown, $record['provider_reference'], raw: ['simulated_status' => 'UNRECOGNISED_STATE']),
            default => new ConnectorResult(ConnectorStatus::Confirmed, $record['provider_reference']),
        };
    }

    public function disburse(DisbursementRequest $request): ConnectorResult
    {
        if ($this->store->get($this->code, $request->reference)) {
            return $this->disbursementStatus($request->reference);
        }
        $record = [
            'type' => 'disbursement',
            'created_at' => now()->getTimestamp(),
            'provider_reference' => $this->simulatedReference(strtoupper(explode('_', $this->code)[0]).'-OUT'),
            'amount' => $request->amount,
        ];
        $this->store->put($this->code, $request->reference, $record);

        return $this->pending($record);
    }

    public function disbursementStatus(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucun versement avec cette référence (simulation).');
        }

        return $this->elapsed($record) < $this->delaySeconds
            ? $this->pending($record)
            : new ConnectorResult(ConnectorStatus::Confirmed, $record['provider_reference']);
    }

    private function pending(array $record): ConnectorResult
    {
        return new ConnectorResult(ConnectorStatus::Pending, $record['provider_reference']);
    }
}
