<?php

namespace App\Domain\Payments\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Models\TransferEvent;
use App\Support\Http\CorrelationId;
use LogicException;

/**
 * Seul point de changement du statut d'un transfert : transition autorisée, horodatages,
 * événement de timeline (ajout seul) et audit des changements sensibles.
 */
class TransferStateMachine
{
    private const AUDITED = [
        TransferStatus::Failed, TransferStatus::RefundPending, TransferStatus::Refunded,
        TransferStatus::RequiresReconciliation, TransferStatus::Cancelled, TransferStatus::PaymentConfirmed,
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function record(Transfer $transfer, ?TransferStatus $from, TransferStatus $to, TransferEventSource $source, array $payload = [], ?string $actorId = null): void
    {
        TransferEvent::create([
            'transfer_id' => $transfer->id,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'actor_id' => $actorId,
            'payload' => $payload ?: null,
            'correlation_id' => CorrelationId::get(),
        ]);
    }

    public function transition(Transfer $transfer, TransferStatus $to, TransferEventSource $source, array $payload = [], ?string $actorId = null): void
    {
        $from = $transfer->status;
        if (! $from->canTransitionTo($to)) {
            throw new LogicException("Transition interdite : {$from->value} → {$to->value} (transfert {$transfer->id}).");
        }

        $transfer->status = $to;
        if ($to->isDelivered() && $transfer->completed_at === null) {
            $transfer->completed_at = now();
        }
        if ($to === TransferStatus::Failed) {
            $transfer->failed_at = now();
        }
        $transfer->save();

        $this->record($transfer, $from, $to, $source, $payload, $actorId);

        if (in_array($to, self::AUDITED, true)) {
            $this->audit->log("transfer.{$to->value}", $actorId ? ActorType::Admin : ActorType::System, $actorId, $transfer, [
                'from' => $from->value, 'source' => $source->value, ...$payload,
            ]);
        }
    }
}
