<?php

namespace Tests\Unit\Payments;

use App\Domain\Payments\Enums\TransferStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransferStatusTest extends TestCase
{
    #[Test]
    public function le_parcours_nominal_est_autorise(): void
    {
        $path = [
            TransferStatus::Quoted, TransferStatus::AwaitingConfirmation, TransferStatus::Authorized,
            TransferStatus::FundsReserved, TransferStatus::Submitted, TransferStatus::Pending,
            TransferStatus::Processing, TransferStatus::Completed,
        ];

        for ($i = 1; $i < count($path); $i++) {
            $this->assertTrue($path[$i - 1]->canTransitionTo($path[$i]), "{$path[$i - 1]->value} -> {$path[$i]->value}");
        }
    }

    #[Test]
    public function un_echec_ne_peut_que_etre_extourne(): void
    {
        $this->assertSame([TransferStatus::Reversed], TransferStatus::Failed->allowedTransitions());
        $this->assertFalse(TransferStatus::Failed->canTransitionTo(TransferStatus::Completed));
    }

    #[Test]
    public function un_etat_inconnu_est_reconcilie_vers_l_etat_reel(): void
    {
        foreach ([TransferStatus::Pending, TransferStatus::Processing, TransferStatus::Completed, TransferStatus::Failed] as $next) {
            $this->assertTrue(TransferStatus::Unknown->canTransitionTo($next));
        }
        $this->assertFalse(TransferStatus::Unknown->canTransitionTo(TransferStatus::Reversed));
    }

    #[Test]
    public function les_etats_terminaux_n_ont_aucune_transition(): void
    {
        $terminal = array_filter(TransferStatus::cases(), fn (TransferStatus $s) => $s->isTerminal());

        $this->assertEqualsCanonicalizing(
            [TransferStatus::Reversed, TransferStatus::Refunded, TransferStatus::Expired, TransferStatus::RejectedKyc, TransferStatus::RejectedRisk],
            array_values($terminal),
        );
    }

    #[Test]
    public function tout_etat_non_terminal_est_atteignable(): void
    {
        $reachable = [TransferStatus::Quoted];
        foreach (TransferStatus::cases() as $status) {
            array_push($reachable, ...$status->allowedTransitions());
        }

        foreach (TransferStatus::cases() as $status) {
            $this->assertContains($status, $reachable, "{$status->value} n'est jamais atteignable");
        }
    }
}
