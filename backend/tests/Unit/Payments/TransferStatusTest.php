<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\Enums\TransferStatus as S;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransferStatusTest extends TestCase
{
    private function assertPath(S ...$path): void
    {
        for ($i = 1; $i < count($path); $i++) {
            $this->assertTrue($path[$i - 1]->canTransitionTo($path[$i]), "{$path[$i - 1]->value} → {$path[$i]->value}");
        }
    }

    #[Test]
    public function parcours_retrait_en_agence(): void
    {
        $this->assertPath(S::Initiated, S::QuoteConfirmed, S::PaymentPending, S::PaymentConfirmed, S::TransferAvailableForPickup, S::PayoutConfirmed);
    }

    #[Test]
    public function parcours_versement_sur_portefeuille(): void
    {
        $this->assertPath(S::Initiated, S::QuoteConfirmed, S::PaymentPending, S::PaymentConfirmed, S::TransferPending, S::TransferConfirmed, S::PayoutConfirmed);
    }

    #[Test]
    public function un_paiement_refuse_echoue_sans_remboursement(): void
    {
        $this->assertTrue(S::PaymentPending->canTransitionTo(S::Failed));
        $this->assertFalse(S::PaymentPending->canTransitionTo(S::RefundPending));
    }

    #[Test]
    public function apres_paiement_confirme_seuls_livraison_ou_remboursement_sont_possibles(): void
    {
        // Des fonds ont été prélevés : l'échec « sec » est interdit, il faut rembourser.
        $this->assertFalse(S::PaymentConfirmed->canTransitionTo(S::Failed));
        $this->assertFalse(S::TransferPending->canTransitionTo(S::Failed));
        $this->assertPath(S::PaymentConfirmed, S::RefundPending, S::Refunded);
        $this->assertPath(S::TransferPending, S::RefundPending);
    }

    #[Test]
    public function un_resultat_inconnu_passe_par_le_rapprochement(): void
    {
        foreach ([S::PaymentPending, S::PaymentConfirmed, S::TransferPending, S::RefundPending] as $status) {
            $this->assertTrue($status->canTransitionTo(S::RequiresReconciliation), $status->value);
        }
        // Le rapprochement ne peut pas « inventer » un succès sans passer par un état constaté.
        $this->assertFalse(S::RequiresReconciliation->canTransitionTo(S::QuoteConfirmed));
    }

    #[Test]
    public function aucun_statut_ne_regresse(): void
    {
        $this->assertFalse(S::TransferConfirmed->canTransitionTo(S::TransferPending));
        $this->assertFalse(S::PayoutConfirmed->canTransitionTo(S::TransferConfirmed));
        $this->assertFalse(S::PaymentConfirmed->canTransitionTo(S::PaymentPending));
    }

    #[Test]
    public function etats_terminaux(): void
    {
        $terminal = array_values(array_filter(S::cases(), fn (S $s) => $s->isTerminal()));
        $this->assertEqualsCanonicalizing([S::PayoutConfirmed, S::Failed, S::Cancelled, S::Refunded], $terminal);
    }

    #[Test]
    public function tout_etat_est_atteignable(): void
    {
        $reachable = [S::Initiated];
        foreach (S::cases() as $status) {
            array_push($reachable, ...$status->allowedTransitions());
        }
        foreach (S::cases() as $status) {
            $this->assertContains($status, $reachable, "{$status->value} n'est jamais atteignable");
        }
    }

    #[Test]
    public function les_etats_detenant_des_fonds_clients_sont_identifies(): void
    {
        $this->assertTrue(S::PaymentConfirmed->holdsCustomerFunds());
        $this->assertTrue(S::RefundPending->holdsCustomerFunds());
        $this->assertFalse(S::PaymentPending->holdsCustomerFunds());
        $this->assertFalse(S::Refunded->holdsCustomerFunds());
    }
}
