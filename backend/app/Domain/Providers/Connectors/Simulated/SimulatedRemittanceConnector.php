<?php

namespace App\Domain\Providers\Connectors\Simulated;

use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Providers\Connectors\Contracts\IncomingTransferConnector;
use App\Domain\Providers\Connectors\Contracts\RemittanceConnector;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\FieldSpec;
use App\Domain\Providers\Connectors\DTO\IncomingTransfer;
use App\Domain\Providers\Connectors\DTO\RemittanceOrder;
use App\Domain\Providers\Connectors\DTO\RemittanceQuote;
use App\Domain\Providers\Connectors\DTO\RemittanceQuoteRequest;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\IncomingScenario;
use App\Domain\Providers\Enums\SimulationScenario;
use Illuminate\Support\Str;

/**
 * Réseau de transfert SIMULÉ (Western Union, MoneyGram, Ria).
 *
 * Frais FICTIFS, références « SIM-… » sans valeur auprès des réseaux réels, et mention
 * « simulation — aucun fonds réel versé » sur tout ce qui est présenté au client.
 */
class SimulatedRemittanceConnector extends SimulatedConnector implements IncomingTransferConnector, RemittanceConnector
{
    /** Grille FICTIVE : [points de base, minimum en XAF]. Aucun lien avec les tarifs réels. */
    private const FICTITIOUS_FEES = [
        'western_union' => [200, 1500],
        'moneygram' => [180, 1200],
        'ria' => [150, 1000],
    ];

    private const PREFIX = ['western_union' => 'WU', 'moneygram' => 'MG', 'ria' => 'RIA'];

    public function capabilities(): array
    {
        return [
            ConnectorCapability::Health, ConnectorCapability::Quote, ConnectorCapability::Validate, ConnectorCapability::CreateTransfer,
            ConnectorCapability::Status, ConnectorCapability::Cancel, ConnectorCapability::Webhook, ConnectorCapability::Reconcile,
        ];
    }

    public function quote(RemittanceQuoteRequest $request): RemittanceQuote
    {
        [$bps, $minimum] = self::FICTITIOUS_FEES[$this->code] ?? [200, 1500];
        $fee = max($minimum, intdiv($request->sendAmount * $bps + 9_999, 10_000)); // arrondi supérieur, en entiers

        return new RemittanceQuote(
            providerFee: $fee,
            fxRate: null, // Cashop applique son propre taux (FxService)
            expiresAt: now()->addMinutes(config('cashop.transfers.quote_ttl_minutes')),
            providerQuoteReference: 'SIM-Q-'.Str::upper(Str::random(10)),
            isSimulated: true,
        );
    }

    public function requiredFields(string $destinationCountry, PayoutMethod $method): array
    {
        return array_values(array_filter([
            new FieldSpec('first_name', 'Prénom', true),
            new FieldSpec('last_name', 'Nom', true),
            new FieldSpec('phone_e164', 'Téléphone du bénéficiaire', $method === PayoutMethod::MobileMoney, 'tel'),
            $method === PayoutMethod::CashPickup ? new FieldSpec('city', 'Ville de retrait souhaitée', false) : null,
        ]));
    }

    public function validate(RemittanceOrder $order): array
    {
        $errors = [];
        foreach ($this->requiredFields($order->destinationCountry, $order->payoutMethod) as $field) {
            if ($field->required && blank($order->beneficiary[$field->name] ?? null)) {
                $errors[$field->name] = "{$field->label} est requis.";
            }
        }

        return $errors;
    }

    public function createTransfer(RemittanceOrder $order): ConnectorResult
    {
        // Idempotence : la même référence renvoie le transfert déjà créé.
        if ($this->store->get($this->code, $order->reference)) {
            return $this->transferStatus($order->reference);
        }

        $scenario = $order->scenario ?? SimulationScenario::Success;
        if ($scenario === SimulationScenario::TransferFailed) {
            throw new ConnectorException('Transfert refusé par le réseau (simulation).', definitive: true, failureCode: 'TRANSFER_REJECTED');
        }

        $record = [
            'type' => 'transfer',
            'scenario' => $scenario->value,
            'payout_method' => $order->payoutMethod->value,
            'created_at' => now()->getTimestamp(),
            'provider_reference' => $this->simulatedReference(self::PREFIX[$this->code] ?? 'NET'),
            'pickup_code' => $order->payoutMethod === PayoutMethod::CashPickup ? 'SIM-'.str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT) : null,
            'cancelled' => false,
        ];
        $this->store->put($this->code, $order->reference, $record);

        if ($scenario === SimulationScenario::TransferTimeout) {
            // Le réseau a bien créé le transfert, mais la réponse n'arrive pas : c'est au
            // rapprochement de le retrouver par la référence Cashop (sans le recréer).
            throw new ConnectorException('Délai dépassé lors de la création du transfert (simulation).', definitive: false, failureCode: 'TIMEOUT');
        }

        return $this->transferStatus($order->reference);
    }

    public function transferStatus(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucun transfert avec cette référence (simulation).');
        }
        if ($record['cancelled']) {
            return new ConnectorResult(ConnectorStatus::Cancelled, $record['provider_reference']);
        }
        if ($record['scenario'] === SimulationScenario::TransferPending->value) {
            return new ConnectorResult(ConnectorStatus::Pending, $record['provider_reference']);
        }
        if ($record['payout_method'] === PayoutMethod::CashPickup->value) {
            return new ConnectorResult(ConnectorStatus::AvailableForPickup, $record['provider_reference'], $record['pickup_code']);
        }

        return $this->elapsed($record) < $this->delaySeconds
            ? new ConnectorResult(ConnectorStatus::Confirmed, $record['provider_reference'])
            : new ConnectorResult(ConnectorStatus::PaidOut, $record['provider_reference']);
    }

    public function cancel(string $reference): ConnectorResult
    {
        $record = $this->store->get($this->code, $reference);
        if (! $record) {
            throw new ConnectorException('Transfert introuvable (simulation).', definitive: true, failureCode: 'NOT_FOUND');
        }
        $record['cancelled'] = true;
        $this->store->put($this->code, $reference, $record);

        return new ConnectorResult(ConnectorStatus::Cancelled, $record['provider_reference']);
    }

    // ─── Réception (transferts entrants simulés) ─────────────────────────────

    /**
     * DÉMONSTRATION : « un proche à l'étranger » envoie un transfert vers le Congo. Renvoie la
     * référence à communiquer au bénéficiaire, qui n'est valable que dans le simulateur.
     */
    public function simulateIncoming(string $receiverFirstName, string $receiverLastName, int $amount, string $currency, string $senderName, string $senderCountry, IncomingScenario $scenario): string
    {
        $reference = 'SIM-IN-'.(self::PREFIX[$this->code] ?? 'NET').'-'.Str::upper(Str::random(8));
        $this->store->put($this->code, "incoming:{$reference}", [
            'amount' => $amount,
            'currency' => $currency,
            'sender_name' => $senderName,
            'sender_country' => $senderCountry,
            'receiver_first_name' => $receiverFirstName,
            'receiver_last_name' => $receiverLastName,
            'scenario' => $scenario->value,
            'payout' => null,
        ]);

        return $reference;
    }

    public function lookupIncoming(string $reference): IncomingTransfer
    {
        $record = $this->store->get($this->code, "incoming:{$reference}");
        if (! $record) {
            throw new ConnectorException('Aucun transfert avec cette référence (simulation).', definitive: true, failureCode: 'NOT_FOUND');
        }

        return new IncomingTransfer(
            reference: $reference,
            status: $record['payout'] && ($record['payout']['status'] ?? null) === 'PAID_OUT' ? 'PAID_OUT' : 'AVAILABLE',
            amount: $record['amount'],
            currency: $record['currency'],
            senderName: $record['sender_name'],
            senderCountry: $record['sender_country'],
            receiverFirstName: $record['receiver_first_name'],
            receiverLastName: $record['receiver_last_name'],
            isSimulated: true,
        );
    }

    public function requestIncomingPayout(string $reference, string $walletProvider, string $msisdn): ConnectorResult
    {
        $key = "incoming:{$reference}";
        $record = $this->store->get($this->code, $key)
            ?? throw new ConnectorException('Aucun transfert avec cette référence (simulation).', definitive: true, failureCode: 'NOT_FOUND');

        // Idempotence : un second appel ne crée pas un second versement.
        if (! $record['payout']) {
            if ($record['scenario'] === IncomingScenario::PayoutFailed->value) {
                throw new ConnectorException('Versement refusé par le réseau (simulation).', definitive: true, failureCode: 'PAYOUT_REJECTED');
            }
            $record['payout'] = [
                'wallet' => $walletProvider,
                'created_at' => now()->getTimestamp(),
                'provider_reference' => $this->simulatedReference((self::PREFIX[$this->code] ?? 'NET').'-PAY'),
                'status' => 'PENDING',
            ];
            $this->store->put($this->code, $key, $record);
        }

        return $this->incomingPayoutStatus($reference);
    }

    public function incomingPayoutStatus(string $reference): ConnectorResult
    {
        $key = "incoming:{$reference}";
        $record = $this->store->get($this->code, $key);
        if (! $record || ! $record['payout']) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Aucun versement pour cette référence (simulation).');
        }
        $payout = $record['payout'];
        if ($record['scenario'] === IncomingScenario::PayoutPending->value || $this->elapsed($payout) < $this->delaySeconds) {
            return new ConnectorResult(ConnectorStatus::Pending, $payout['provider_reference']);
        }
        if ($payout['status'] !== 'PAID_OUT') {
            $record['payout']['status'] = 'PAID_OUT';
            $this->store->put($this->code, $key, $record);
        }

        return new ConnectorResult(ConnectorStatus::PaidOut, $payout['provider_reference']);
    }
}
