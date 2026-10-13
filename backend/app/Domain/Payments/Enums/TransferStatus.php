<?php

namespace App\Domain\Payments\Enums;

use App\Support\Enums\HasValues;

/**
 * Machine à états d'un transfert (docs/TRANSFER-STATES.md).
 *
 * Cashop ne détient pas de fonds : le client finance chaque transfert par Mobile Money, puis le
 * transfert est créé auprès du réseau. Un statut ne progresse que sur confirmation effective du
 * prestataire ; un résultat inconnu mène à REQUIRES_RECONCILIATION, jamais à un succès supposé.
 */
enum TransferStatus: string
{
    use HasValues;

    case Initiated = 'INITIATED';
    case QuotePending = 'QUOTE_PENDING';
    case QuoteConfirmed = 'QUOTE_CONFIRMED';
    case PaymentPending = 'PAYMENT_PENDING';
    case PaymentConfirmed = 'PAYMENT_CONFIRMED';
    case TransferPending = 'TRANSFER_PENDING';
    case TransferConfirmed = 'TRANSFER_CONFIRMED';
    case TransferAvailableForPickup = 'TRANSFER_AVAILABLE_FOR_PICKUP';
    case PayoutPending = 'PAYOUT_PENDING';
    case PayoutConfirmed = 'PAYOUT_CONFIRMED';
    case Failed = 'FAILED';
    case CancelPending = 'CANCEL_PENDING';
    case Cancelled = 'CANCELLED';
    case RefundPending = 'REFUND_PENDING';
    case Refunded = 'REFUNDED';
    case RequiresReconciliation = 'REQUIRES_RECONCILIATION';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Initiated => [self::QuotePending, self::QuoteConfirmed, self::Cancelled],
            self::QuotePending => [self::QuoteConfirmed, self::Failed, self::Cancelled],
            self::QuoteConfirmed => [self::PaymentPending, self::Cancelled],
            // Paiement refusé : aucun fonds prélevé, échec sans remboursement.
            self::PaymentPending => [self::PaymentConfirmed, self::Failed, self::RequiresReconciliation],
            // Fonds prélevés : seule issue sans livraison = remboursement.
            self::PaymentConfirmed => [self::TransferPending, self::TransferConfirmed, self::TransferAvailableForPickup, self::RefundPending, self::RequiresReconciliation],
            self::TransferPending => [self::TransferConfirmed, self::TransferAvailableForPickup, self::PayoutPending, self::RefundPending, self::RequiresReconciliation],
            // Le réseau peut encore annuler (transfert non retiré, versement rejeté) : remboursement.
            self::TransferConfirmed => [self::TransferAvailableForPickup, self::PayoutPending, self::PayoutConfirmed, self::CancelPending, self::RefundPending],
            self::TransferAvailableForPickup => [self::PayoutConfirmed, self::CancelPending, self::RefundPending],
            self::PayoutPending => [self::PayoutConfirmed, self::RefundPending, self::RequiresReconciliation],
            self::CancelPending => [self::RefundPending, self::TransferConfirmed, self::TransferAvailableForPickup],
            self::RefundPending => [self::Refunded, self::RequiresReconciliation],
            // Le rapprochement constate l'état réel chez les prestataires.
            self::RequiresReconciliation => [
                self::PaymentConfirmed, self::Failed, self::TransferPending, self::TransferConfirmed,
                self::TransferAvailableForPickup, self::PayoutConfirmed, self::RefundPending, self::Refunded,
            ],
            self::PayoutConfirmed, self::Failed, self::Cancelled, self::Refunded => [],
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

    /** Fonds du client prélevés et non restitués : la plateforme a une obligation envers lui. */
    public function holdsCustomerFunds(): bool
    {
        return in_array($this, [
            self::PaymentConfirmed, self::TransferPending, self::RefundPending, self::CancelPending, self::PayoutPending,
        ], true);
    }

    /** Le transfert a été accepté par le réseau (succès côté expéditeur). */
    public function isDelivered(): bool
    {
        return in_array($this, [self::TransferConfirmed, self::TransferAvailableForPickup, self::PayoutConfirmed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Créé',
            self::QuotePending => 'Devis en cours',
            self::QuoteConfirmed => 'Devis accepté',
            self::PaymentPending => 'En attente de votre validation Mobile Money',
            self::PaymentConfirmed => 'Paiement confirmé',
            self::TransferPending => 'Transfert en cours auprès du réseau',
            self::TransferConfirmed => 'Transfert confirmé par le réseau',
            self::TransferAvailableForPickup => 'Disponible au retrait en agence',
            self::PayoutPending => 'Versement en cours',
            self::PayoutConfirmed => 'Versé au bénéficiaire',
            self::Failed => 'Échoué',
            self::CancelPending => 'Annulation en cours',
            self::Cancelled => 'Annulé',
            self::RefundPending => 'Remboursement en cours',
            self::Refunded => 'Remboursé',
            self::RequiresReconciliation => 'Vérification en cours auprès des prestataires',
        };
    }
}
