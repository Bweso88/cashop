<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

/**
 * Cycle de vie d'un transfert (docs/ARCHITECTURE.md §5).
 */
enum TransferStatus: string
{
    use HasValues;

    case Quoted = 'QUOTED';
    case AwaitingConfirmation = 'AWAITING_CONFIRMATION';
    case Authorized = 'AUTHORIZED';
    case FundsReserved = 'FUNDS_RESERVED';
    case Submitted = 'SUBMITTED';
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Reversed = 'REVERSED';
    case Unknown = 'UNKNOWN';
    case CancelRequested = 'CANCEL_REQUESTED';
    case Cancelled = 'CANCELLED';
    case RefundPending = 'REFUND_PENDING';
    case Refunded = 'REFUNDED';
    case Expired = 'EXPIRED';
    case RejectedKyc = 'REJECTED_KYC';
    case RejectedRisk = 'REJECTED_RISK';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Quoted => [self::AwaitingConfirmation, self::Expired],
            self::AwaitingConfirmation => [self::Authorized, self::RejectedKyc, self::RejectedRisk, self::Expired, self::Cancelled],
            self::Authorized => [self::FundsReserved, self::Cancelled],
            self::FundsReserved => [self::Submitted, self::Failed],
            self::Submitted => [self::Pending, self::Processing, self::Completed, self::Failed, self::Unknown],
            self::Pending => [self::Processing, self::Completed, self::Failed, self::Unknown, self::CancelRequested],
            self::Processing => [self::Completed, self::Failed, self::Unknown, self::CancelRequested],
            // Réconciliation : l'état réel est relu chez le provider.
            self::Unknown => [self::Pending, self::Processing, self::Completed, self::Failed],
            // L'annulation peut être refusée par le provider : retour à l'état réel.
            self::CancelRequested => [self::Cancelled, self::Pending, self::Processing, self::Completed, self::Failed],
            // Extourne comptable uniquement si des fonds avaient été réservés.
            self::Cancelled => [self::Reversed],
            self::Failed => [self::Reversed],
            self::Completed => [self::RefundPending],
            self::RefundPending => [self::Refunded, self::Completed],
            self::Reversed, self::Refunded, self::Expired, self::RejectedKyc, self::RejectedRisk => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
