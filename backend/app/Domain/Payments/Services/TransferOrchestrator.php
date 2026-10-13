<?php

namespace App\Domain\Payments\Services;

use App\Domain\Identity\Services\PinService;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\LedgerTransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payments\DTO\CreateTransferInput;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Enums\QuoteStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\Beneficiary;
use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Models\TransferQuote;
use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\Contracts\RemittanceConnector;
use App\Domain\Providers\Connectors\Contracts\WalletConnector;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Connectors\DTO\RemittanceOrder;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Enums\SimulationScenario;
use App\Models\User;
use App\Support\Http\ApiException;
use App\Support\Http\CorrelationId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestration d'un envoi : paiement Mobile Money → création du transfert → suivi → reçu.
 *
 * Le paiement et le transfert ne peuvent pas être atomiques (deux prestataires distincts) :
 *  1. Un transfert ne passe à l'étape suivante que sur confirmation effective du prestataire.
 *  2. Une seule collecte par transfert (index unique en base) : jamais de double débit.
 *  3. Refus explicite du réseau après paiement confirmé → remboursement intégral, suivi jusqu'à confirmation.
 *  4. Résultat inconnu (délai, réponse illisible) → REQUIRES_RECONCILIATION : on interroge le
 *     prestataire avec la référence Cashop avant toute décision ; jamais de nouvelle tentative à l'aveugle.
 *  5. Verrou par transfert : webhook, relecture et tâche planifiée ne le traitent jamais en même temps.
 */
class TransferOrchestrator
{
    public function __construct(
        private readonly ConnectorRegistry $connectors,
        private readonly TransferStateMachine $states,
        private readonly LedgerService $ledger,
        private readonly ReceiptService $receipts,
        private readonly PinService $pins,
        private readonly LimitChecker $limits,
    ) {}

    // ─── Création ────────────────────────────────────────────────────────────

    public function create(User $user, CreateTransferInput $input, string $idempotencyKey): Transfer
    {
        $existing = Transfer::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->quote_id !== $input->quoteId) {
                throw new ApiException('IDEMPOTENCY_CONFLICT', 'Cette clé d’idempotence a déjà servi pour un autre transfert.', 409);
            }

            return $existing; // rejeu : aucun nouveau débit
        }

        $this->pins->verify($user, $input->pin);

        $transfer = DB::transaction(function () use ($user, $input, $idempotencyKey) {
            /** @var TransferQuote|null $quote */
            $quote = TransferQuote::query()->whereKey($input->quoteId)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $quote) {
                throw new ApiException('NOT_FOUND', 'Devis introuvable.', 404);
            }
            if (! $quote->isUsable()) {
                throw new ApiException('QUOTE_EXPIRED', 'Ce devis a expiré ou a déjà été utilisé. Demandez un nouveau devis.', 409);
            }
            $beneficiary = Beneficiary::query()->whereKey($input->beneficiaryId)->where('user_id', $user->id)->first();
            if (! $beneficiary) {
                throw new ApiException('NOT_FOUND', 'Bénéficiaire introuvable.', 404);
            }
            if ($beneficiary->country_code !== $quote->destination_country) {
                throw new ApiException('VALIDATION_ERROR', 'Le pays du bénéficiaire ne correspond pas au devis.', 422, ['beneficiary_id' => ['Pays différent du devis.']]);
            }
            if ($input->scenario !== null && $quote->environment !== ProviderMode::Mock) {
                throw new ApiException('SIMULATION_FORBIDDEN', 'Les scénarios de simulation ne sont disponibles qu’en environnement de démonstration.', 422);
            }
            $this->limits->assertAllowed($user, $quote->send_amount, $quote->send_currency);

            $quote->loadMissing('provider', 'fundingProvider');
            $order = $this->order($user, $quote, $beneficiary, 'validation', $input->scenario);
            $errors = $this->remittance($quote->provider->code, $quote->environment)->validate($order);
            if ($errors !== []) {
                throw new ApiException('VALIDATION_ERROR', 'Informations du bénéficiaire incomplètes pour ce réseau.', 422, array_map(fn ($m) => [$m], $errors));
            }

            $quote->update(['status' => QuoteStatus::Used]);

            $transfer = Transfer::create([
                'user_id' => $user->id,
                'quote_id' => $quote->id,
                'beneficiary_id' => $beneficiary->id,
                'provider_id' => $quote->provider_id,
                'funding_provider_id' => $quote->funding_provider_id,
                'funding_account' => $input->fundingMsisdn,
                'funding_account_hash' => hash_hmac('sha256', $input->fundingMsisdn, (string) config('app.key')),
                'environment' => $quote->environment,
                'status' => TransferStatus::Initiated,
                'send_amount' => $quote->send_amount,
                'send_currency' => $quote->send_currency,
                'receive_amount' => $quote->receive_amount,
                'receive_currency' => $quote->receive_currency,
                'fx_rate' => $quote->fx_rate,
                'total_fees' => $quote->total_fees,
                'total_debit' => $quote->total_debit,
                'payment_method' => $quote->payment_method,
                'payout_method' => $quote->payout_method,
                'destination_country' => $quote->destination_country,
                'purpose' => $input->purpose,
                'note' => $input->note,
                'simulation_scenario' => $input->scenario?->value,
                'correlation_id' => CorrelationId::get(),
                'external_reference' => self::newReference(),
                'idempotency_key' => $idempotencyKey,
            ]);
            $this->states->record($transfer, null, TransferStatus::Initiated, TransferEventSource::Api, actorId: null);
            $this->states->transition($transfer, TransferStatus::QuoteConfirmed, TransferEventSource::Api, ['quote_id' => $quote->id]);

            PaymentAttempt::create([
                'transfer_id' => $transfer->id,
                'provider_id' => $quote->funding_provider_id,
                'direction' => PaymentDirection::Collection,
                'environment' => $quote->environment,
                'account' => $input->fundingMsisdn,
                'account_hash' => $transfer->funding_account_hash,
                'amount' => $quote->total_debit,
                'currency' => $quote->send_currency,
                'status' => PaymentAttemptStatus::Pending,
            ]);
            $this->states->transition($transfer, TransferStatus::PaymentPending, TransferEventSource::Api);
            $beneficiary->update(['last_used_at' => now()]);

            return $transfer;
        });

        $this->initiatePayment($transfer);

        $fresh = $transfer->fresh();
        $fresh->wasRecentlyCreated = true;

        return $fresh;
    }

    /** Demande de débit au portefeuille. Le client valide sur son téléphone (Cashop ne demande jamais son PIN Mobile Money). */
    private function initiatePayment(Transfer $transfer): void
    {
        $attempt = $transfer->collection()->firstOrFail();
        try {
            $result = $this->wallet($transfer)->collect(new CollectionRequest(
                reference: $attempt->id,
                msisdn: $attempt->account,
                amount: $attempt->amount,
                currency: $attempt->currency,
                description: "Cashop {$transfer->external_reference}",
                scenario: $this->scenario($transfer),
            ));
            $this->applyCollection($transfer, $attempt, $result);
        } catch (ConnectorException $e) {
            $e->definitive
                ? $this->failPayment($transfer, $attempt, $e->failureCode ?? 'PAYMENT_REJECTED', $e->getMessage())
                : $this->toReconciliation($transfer, 'PAYMENT_INITIATION_UNCERTAIN', $e->getMessage());
        }
    }

    // ─── Progression ─────────────────────────────────────────────────────────

    /**
     * Fait avancer le transfert d'après l'état réel chez les prestataires.
     * Appelé par la relecture client, les webhooks et la tâche planifiée.
     */
    public function advance(Transfer $transfer): Transfer
    {
        $lock = Cache::lock("transfer-advance:{$transfer->id}", 30);
        if (! $lock->get()) {
            return $transfer->fresh(); // déjà en cours de traitement ailleurs
        }

        try {
            for ($step = 0; $step < 6; $step++) {
                $transfer = $transfer->fresh();
                $before = $transfer->status;
                try {
                    match ($before) {
                        TransferStatus::PaymentPending => $this->checkPayment($transfer),
                        TransferStatus::PaymentConfirmed => $this->submitTransfer($transfer),
                        TransferStatus::TransferPending, TransferStatus::TransferConfirmed, TransferStatus::PayoutPending => $this->checkTransfer($transfer),
                        TransferStatus::RefundPending => $this->checkRefund($transfer),
                        TransferStatus::RequiresReconciliation => app(ReconciliationService::class)->reconcile($transfer),
                        default => null,
                    };
                } catch (ConnectorUnavailableException $e) {
                    Log::warning('Connecteur indisponible', ['transfer' => $transfer->id, 'error' => $e->getMessage()]);
                    break;
                }
                if ($transfer->fresh()->status === $before) {
                    break;
                }
            }
            $transfer = $transfer->fresh();
            $transfer->forceFill([
                'status_poll_count' => $transfer->status_poll_count + 1,
                'next_status_poll_at' => $transfer->status->isTerminal() || $transfer->status === TransferStatus::TransferAvailableForPickup ? null : now()->addSeconds(30),
            ])->save();

            return $transfer;
        } finally {
            $lock->release();
        }
    }

    private function checkPayment(Transfer $transfer): void
    {
        $attempt = $transfer->collection()->firstOrFail();
        try {
            $result = $this->wallet($transfer)->collectionStatus($attempt->id);
        } catch (ConnectorException $e) {
            Log::info('Statut de paiement momentanément indisponible', ['transfer' => $transfer->id, 'error' => $e->getMessage()]);

            return;
        }
        $this->applyCollection($transfer, $attempt, $result);
    }

    public function applyCollection(Transfer $transfer, PaymentAttempt $attempt, ConnectorResult $result): void
    {
        match ($result->status) {
            ConnectorStatus::Confirmed => $this->confirmPayment($transfer, $attempt, $result),
            ConnectorStatus::Failed, ConnectorStatus::Cancelled => $this->failPayment($transfer, $attempt, $result->failureCode ?? 'PAYMENT_FAILED', $result->failureReason ?? 'Paiement refusé.'),
            ConnectorStatus::Pending => $this->paymentStillPending($transfer, $attempt, $result),
            default => $this->unknownPayment($transfer, $attempt, $result),
        };
    }

    private function confirmPayment(Transfer $transfer, PaymentAttempt $attempt, ConnectorResult $result): void
    {
        DB::transaction(function () use ($transfer, $attempt, $result) {
            $attempt->update(['status' => PaymentAttemptStatus::Confirmed, 'provider_reference' => $result->providerReference, 'confirmed_at' => now()]);
            $transfer->payment_confirmed_at = now();
            $wallet = $transfer->fundingProvider->code;
            $this->ledger->post(LedgerTransactionType::TransferReserve, "payment:{$attempt->id}", $transfer->send_currency, 'transfer', $transfer->id,
                "Paiement Mobile Money confirmé {$transfer->external_reference}", [
                    ["collection_clearing:{$wallet}:{$transfer->send_currency}", EntryDirection::Debit, $transfer->total_debit],
                    ["in_flight:{$transfer->send_currency}", EntryDirection::Credit, $transfer->total_debit],
                ]);
            $this->states->transition($transfer, TransferStatus::PaymentConfirmed, TransferEventSource::Polling, ['payment_reference' => $result->providerReference]);
        });
    }

    private function failPayment(Transfer $transfer, PaymentAttempt $attempt, string $code, string $reason): void
    {
        DB::transaction(function () use ($transfer, $attempt, $code, $reason) {
            $attempt->update(['status' => PaymentAttemptStatus::Failed, 'failure_reason' => $reason]);
            $transfer->forceFill(['failure_code' => $code, 'failure_reason' => $reason]);
            // Aucun fonds prélevé : échec sans remboursement.
            $this->states->transition($transfer, TransferStatus::Failed, TransferEventSource::Polling, ['reason' => $reason]);
        });
    }

    private function paymentStillPending(Transfer $transfer, PaymentAttempt $attempt, ConnectorResult $result): void
    {
        if ($result->providerReference && ! $attempt->provider_reference) {
            $attempt->update(['provider_reference' => $result->providerReference]);
        }
        if ($attempt->created_at->lt(now()->subMinutes(config('cashop.transfers.payment_timeout_minutes')))) {
            $attempt->update(['status' => PaymentAttemptStatus::Unknown]);
            $this->toReconciliation($transfer, 'PAYMENT_TIMEOUT', 'Aucune confirmation de l’opérateur dans le délai imparti.');
        }
    }

    private function unknownPayment(Transfer $transfer, PaymentAttempt $attempt, ConnectorResult $result): void
    {
        $attempt->update(['status' => PaymentAttemptStatus::Unknown]);
        $this->toReconciliation($transfer, 'PAYMENT_UNKNOWN_RESPONSE', 'Réponse de l’opérateur non reconnue.', ['raw' => $result->raw]);
    }

    /** Création du transfert auprès du réseau, une fois le financement confirmé. */
    private function submitTransfer(Transfer $transfer): void
    {
        if ($transfer->submitted_at !== null) {
            // Une soumission a déjà eu lieu sans réponse exploitable : on vérifie, on ne renvoie pas.
            $this->toReconciliation($transfer, 'SUBMISSION_UNCERTAIN', 'Soumission précédente sans réponse exploitable.');

            return;
        }
        $transfer->loadMissing('quote', 'beneficiary', 'user.profile', 'provider');
        // Résolution avant de marquer la soumission : si le connecteur est indisponible (environnement
        // modifié, production désactivée), rien n'a été envoyé et le transfert reste en attente.
        $connector = $this->remittance($transfer->provider->code, $transfer->environment);
        $transfer->forceFill(['submitted_at' => now()])->save();

        try {
            $result = $connector->createTransfer(
                $this->order($transfer->user, $transfer->quote, $transfer->beneficiary, $transfer->external_reference, $this->scenario($transfer)),
            );
        } catch (ConnectorException $e) {
            $e->definitive
                ? $this->startRefund($transfer, $e->failureCode ?? 'TRANSFER_REJECTED', $e->getMessage())
                : $this->toReconciliation($transfer, 'TRANSFER_SUBMISSION_UNCERTAIN', $e->getMessage());

            return;
        }
        $this->applyTransferResult($transfer, $result);
    }

    private function checkTransfer(Transfer $transfer): void
    {
        try {
            $result = $this->remittance($transfer->provider->code, $transfer->environment)->transferStatus($transfer->external_reference);
        } catch (ConnectorException $e) {
            Log::info('Statut du transfert momentanément indisponible', ['transfer' => $transfer->id, 'error' => $e->getMessage()]);

            return;
        }
        $this->applyTransferResult($transfer, $result);
    }

    public function applyTransferResult(Transfer $transfer, ConnectorResult $result): void
    {
        $updates = array_filter(['provider_reference' => $result->providerReference, 'pickup_code' => $result->pickupCode]);
        if ($updates) {
            $transfer->forceFill($updates)->save();
        }

        $target = match ($result->status) {
            ConnectorStatus::Pending => TransferStatus::TransferPending,
            ConnectorStatus::Confirmed => TransferStatus::TransferConfirmed,
            ConnectorStatus::AvailableForPickup => TransferStatus::TransferAvailableForPickup,
            ConnectorStatus::PaidOut => TransferStatus::PayoutConfirmed,
            ConnectorStatus::Failed, ConnectorStatus::Cancelled => null,
            ConnectorStatus::Unknown => TransferStatus::RequiresReconciliation,
        };

        if ($target === null) {
            $this->startRefund($transfer, $result->failureCode ?? 'TRANSFER_FAILED', $result->failureReason ?? 'Transfert refusé ou annulé par le réseau.');

            return;
        }
        if ($target === TransferStatus::RequiresReconciliation) {
            $this->toReconciliation($transfer, 'TRANSFER_UNKNOWN_RESPONSE', 'Réponse du réseau non reconnue.', ['raw' => $result->raw]);

            return;
        }
        if ($target === $transfer->status) {
            return;
        }
        // Versement direct constaté : on passe par l'étape « confirmé » pour une timeline complète.
        if ($target === TransferStatus::PayoutConfirmed && ! $transfer->status->canTransitionTo($target) && $transfer->status->canTransitionTo(TransferStatus::TransferConfirmed)) {
            $this->deliver($transfer, TransferStatus::TransferConfirmed, $result);
        }
        if (! $transfer->status->canTransitionTo($target)) {
            // Un statut ne régresse jamais (ex. « en attente » renvoyé après une confirmation).
            Log::warning('Statut prestataire ignoré : transition non autorisée', ['transfer' => $transfer->id, 'from' => $transfer->status->value, 'reported' => $result->status->value]);

            return;
        }
        $this->deliver($transfer, $target, $result);
    }

    private function deliver(Transfer $transfer, TransferStatus $target, ConnectorResult $result): void
    {
        DB::transaction(function () use ($transfer, $target, $result) {
            $this->states->transition($transfer, $target, TransferEventSource::Polling, array_filter(['provider_reference' => $result->providerReference]));
            if ($target->isDelivered()) {
                $this->settle($transfer);
            }
        });
        if ($target->isDelivered()) {
            $this->receipts->issue($transfer->fresh(), 'TRANSFER');
        }
    }

    /** Écritures de livraison : le réseau devient créancier, Cashop perçoit ses frais de service. */
    private function settle(Transfer $transfer): void
    {
        $transfer->loadMissing('quote', 'provider');
        $providerFee = (int) collect($transfer->quote->fee_breakdown)->where('component', 'PROVIDER')->sum('amount');
        $currency = $transfer->send_currency;
        $this->ledger->post(LedgerTransactionType::TransferSettle, "settle:{$transfer->id}", $currency, 'transfer', $transfer->id,
            "Transfert livré {$transfer->external_reference}", [
                ["in_flight:{$currency}", EntryDirection::Debit, $transfer->total_debit],
                ["remittance_payable:{$transfer->provider->code}:{$currency}", EntryDirection::Credit, $transfer->send_amount + $providerFee],
                ["fee_revenue:{$currency}", EntryDirection::Credit, $transfer->total_fees - $providerFee],
            ]);
    }

    // ─── Remboursement ───────────────────────────────────────────────────────

    /** Paiement confirmé mais transfert impossible : restitution intégrale sur le portefeuille d'origine. */
    public function startRefund(Transfer $transfer, string $code, string $reason, ?string $actorId = null): void
    {
        $refund = DB::transaction(function () use ($transfer, $code, $reason, $actorId) {
            $transfer->forceFill(['failure_code' => $code, 'failure_reason' => $reason]);
            $this->states->transition($transfer, TransferStatus::RefundPending, $actorId ? TransferEventSource::Admin : TransferEventSource::System, ['reason' => $reason], $actorId);

            $attempt = PaymentAttempt::create([
                'transfer_id' => $transfer->id,
                'provider_id' => $transfer->funding_provider_id,
                'direction' => PaymentDirection::Disbursement,
                'environment' => $transfer->environment,
                'account' => $transfer->funding_account,
                'account_hash' => $transfer->funding_account_hash,
                'amount' => $transfer->total_debit,
                'currency' => $transfer->send_currency,
                'status' => PaymentAttemptStatus::Pending,
            ]);

            return Refund::create([
                'transfer_id' => $transfer->id,
                'amount' => $transfer->total_debit,
                'currency_code' => $transfer->send_currency,
                'reason' => $reason,
                'status' => RefundStatus::Processing,
                'requested_by' => $actorId,
                'payment_attempt_id' => $attempt->id,
                'idempotency_key' => "refund:{$transfer->id}",
            ]);
        });

        try {
            $result = $this->wallet($transfer)->disburse(new DisbursementRequest(
                reference: $refund->payment_attempt_id,
                msisdn: $transfer->funding_account,
                amount: $refund->amount,
                currency: $refund->currency_code,
                description: "Remboursement Cashop {$transfer->external_reference}",
            ));
            $this->applyRefund($transfer->fresh(), $refund->fresh(), $result);
        } catch (ConnectorException $e) {
            // Remboursement demandé mais résultat inconnu : il sera vérifié (jamais redemandé à l'aveugle).
            Log::warning('Remboursement : résultat incertain', ['transfer' => $transfer->id, 'error' => $e->getMessage()]);
        }
    }

    private function checkRefund(Transfer $transfer): void
    {
        $refund = $transfer->refunds()->latest()->firstOrFail();
        try {
            $result = $this->wallet($transfer)->disbursementStatus($refund->payment_attempt_id);
        } catch (ConnectorException) {
            return;
        }
        $this->applyRefund($transfer, $refund, $result);
    }

    public function applyRefund(Transfer $transfer, Refund $refund, ConnectorResult $result): void
    {
        if ($result->status === ConnectorStatus::Confirmed) {
            DB::transaction(function () use ($transfer, $refund, $result) {
                $refund->paymentAttempt->update(['status' => PaymentAttemptStatus::Confirmed, 'provider_reference' => $result->providerReference, 'confirmed_at' => now()]);
                $refund->update(['status' => RefundStatus::Completed, 'provider_reference' => $result->providerReference]);
                $wallet = $transfer->fundingProvider->code;
                $this->ledger->post(LedgerTransactionType::Refund, "refund:{$refund->id}", $transfer->send_currency, 'transfer', $transfer->id,
                    "Remboursement {$transfer->external_reference}", [
                        ["in_flight:{$transfer->send_currency}", EntryDirection::Debit, $refund->amount],
                        ["collection_clearing:{$wallet}:{$transfer->send_currency}", EntryDirection::Credit, $refund->amount],
                    ]);
                $this->states->transition($transfer, TransferStatus::Refunded, TransferEventSource::Polling, ['refund_reference' => $result->providerReference]);
            });
            $this->receipts->issue($transfer->fresh(), 'REFUND');
        } elseif (in_array($result->status, [ConnectorStatus::Failed, ConnectorStatus::Unknown], true)) {
            $refund->paymentAttempt->update(['status' => PaymentAttemptStatus::Unknown]);
            $this->toReconciliation($transfer, 'REFUND_NOT_CONFIRMED', $result->failureReason ?? 'Remboursement non confirmé par l’opérateur.');
        }
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    public function toReconciliation(Transfer $transfer, string $code, string $reason, array $evidence = []): void
    {
        $transfer->forceFill(['failure_code' => $code, 'failure_reason' => $reason]);
        $this->states->transition($transfer, TransferStatus::RequiresReconciliation, TransferEventSource::System, ['reason' => $reason, 'code' => $code, ...$evidence]);
    }

    public function wallet(Transfer $transfer): WalletConnector
    {
        $transfer->loadMissing('fundingProvider');

        return $this->connectors->wallet($transfer->fundingProvider->code, $transfer->environment);
    }

    public function remittance(string $code, ProviderMode $environment): RemittanceConnector
    {
        return $this->connectors->remittance($code, $environment);
    }

    private function scenario(Transfer $transfer): ?SimulationScenario
    {
        return $transfer->simulation_scenario ? SimulationScenario::from($transfer->simulation_scenario) : null;
    }

    private function order(User $user, TransferQuote $quote, Beneficiary $beneficiary, string $reference, ?SimulationScenario $scenario): RemittanceOrder
    {
        $profile = $user->profile;

        return new RemittanceOrder(
            reference: $reference,
            destinationCountry: $quote->destination_country,
            payoutMethod: $quote->payout_method,
            sendAmount: $quote->send_amount,
            sendCurrency: $quote->send_currency,
            receiveAmount: $quote->receive_amount,
            receiveCurrency: $quote->receive_currency,
            sender: ['first_name' => $profile?->first_name, 'last_name' => $profile?->last_name, 'phone_e164' => $user->phone_e164, 'country' => QuoteService::ORIGIN_COUNTRY],
            beneficiary: [
                'first_name' => $beneficiary->first_name, 'last_name' => $beneficiary->last_name,
                'phone_e164' => $beneficiary->phone_e164, 'city' => $beneficiary->city, 'country' => $beneficiary->country_code,
                ...($beneficiary->details ?? []),
            ],
            providerQuoteReference: $quote->provider_quote_reference,
            scenario: $scenario,
        );
    }

    /** Référence Cashop lisible, sans ambiguïté (pas de 0/O, 1/I). */
    public static function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $chars = '';
        for ($i = 0; $i < 8; $i++) {
            $chars .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'CSH-'.substr($chars, 0, 4).'-'.substr($chars, 4);
    }
}
