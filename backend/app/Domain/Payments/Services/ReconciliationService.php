<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\ReconciliationRecord;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Enums\ConnectorStatus;

/**
 * Rapprochement d'un transfert au résultat incertain : on interroge chaque prestataire avec la
 * référence Cashop et on constate l'état réel. Chaque vérification est tracée (preuves) dans
 * reconciliation_records, même quand elle ne permet pas encore de conclure.
 *
 * Ordre des vérifications :
 *  1. Un remboursement est en cours → son statut décide (REFUNDED ou toujours en attente).
 *  2. Le paiement n'est pas confirmé → statut de la collecte (confirmé, refusé, ou toujours inconnu).
 *  3. Paiement confirmé → statut du transfert chez le réseau, interrogé par référence :
 *     - trouvé : on reprend l'état réel (aucune seconde création) ;
 *     - introuvable : le réseau ne l'a jamais créé → remboursement intégral du client.
 */
class ReconciliationService
{
    public function __construct(
        private readonly TransferOrchestrator $orchestrator,
        private readonly TransferStateMachine $states,
    ) {}

    public function reconcile(Transfer $transfer, ?string $actorId = null): ReconciliationRecord
    {
        $before = $transfer->status;
        $evidence = [];
        $paymentSeen = null;
        $transferSeen = null;

        try {
            $refundAttempt = $transfer->paymentAttempts()->where('direction', PaymentDirection::Disbursement)->latest()->first();
            $collection = $transfer->collection()->first();

            if ($refundAttempt) {
                $result = $this->orchestrator->wallet($transfer)->disbursementStatus($refundAttempt->id);
                $evidence['refund'] = $this->evidence($result);
                $paymentSeen = 'REFUND:'.$result->status->value;
                if ($result->status === ConnectorStatus::Confirmed) {
                    $this->states->transition($transfer, TransferStatus::RefundPending, TransferEventSource::System, ['reconciliation' => true], $actorId);
                    $this->orchestrator->applyRefund($transfer->fresh(), $transfer->refunds()->latest()->firstOrFail(), $result);
                }
            } elseif ($collection && $collection->status !== PaymentAttemptStatus::Confirmed) {
                $result = $this->orchestrator->wallet($transfer)->collectionStatus($collection->id);
                $evidence['payment'] = $this->evidence($result);
                $paymentSeen = $result->status->value;
                if ($result->status === ConnectorStatus::Confirmed) {
                    $this->orchestrator->applyCollection($transfer, $collection, $result);
                } elseif ($result->status === ConnectorStatus::Failed) {
                    $collection->update(['status' => PaymentAttemptStatus::Failed, 'failure_reason' => $result->failureReason]);
                    $transfer->forceFill(['failure_code' => $result->failureCode, 'failure_reason' => $result->failureReason]);
                    $this->states->transition($transfer, TransferStatus::Failed, TransferEventSource::System, ['reconciliation' => true], $actorId);
                }
            } else {
                $result = $this->orchestrator->remittance($transfer->provider->code, $transfer->environment)->transferStatus($transfer->external_reference);
                $evidence['transfer'] = $this->evidence($result);
                $transferSeen = $result->status->value;
                if ($result->status === ConnectorStatus::Failed && $result->failureCode === 'NOT_FOUND') {
                    $this->orchestrator->startRefund($transfer, 'TRANSFER_NOT_CREATED', 'Le réseau n’a jamais créé ce transfert : remboursement du client.', $actorId);
                } elseif ($result->status !== ConnectorStatus::Unknown) {
                    $this->resumeFromReconciliation($transfer, $result);
                }
            }
        } catch (ConnectorException $e) {
            $evidence['error'] = $e->getMessage();
        }

        $after = $transfer->fresh()->status;

        return ReconciliationRecord::create([
            'transfer_id' => $transfer->id,
            'status_before' => $before->value,
            'status_after' => $after->value,
            'payment_status_seen' => $paymentSeen,
            'transfer_status_seen' => $transferSeen,
            'outcome' => $after === $before ? ($this->isOverdue($transfer) ? 'ESCALATED' : 'STILL_UNKNOWN') : 'RESOLVED',
            'evidence' => $evidence,
            'actor_id' => $actorId,
        ]);
    }

    /** Le réseau connaît le transfert : on reprend son état réel. */
    private function resumeFromReconciliation(Transfer $transfer, ConnectorResult $result): void
    {
        if ($result->status === ConnectorStatus::Failed || $result->status === ConnectorStatus::Cancelled) {
            $this->orchestrator->startRefund($transfer, $result->failureCode ?? 'TRANSFER_FAILED', $result->failureReason ?? 'Transfert refusé par le réseau.');

            return;
        }
        if ($result->status === ConnectorStatus::Pending) {
            $this->states->transition($transfer, TransferStatus::TransferPending, TransferEventSource::System, ['reconciliation' => true]);

            return;
        }
        $this->orchestrator->applyTransferResult($transfer, $result);
    }

    private function isOverdue(Transfer $transfer): bool
    {
        return $transfer->updated_at->lt(now()->subHours(config('cashop.transfers.reconciliation_escalation_hours')));
    }

    private function evidence(ConnectorResult $result): array
    {
        return array_filter([
            'status' => $result->status->value,
            'provider_reference' => $result->providerReference,
            'failure_code' => $result->failureCode,
            'failure_reason' => $result->failureReason,
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
