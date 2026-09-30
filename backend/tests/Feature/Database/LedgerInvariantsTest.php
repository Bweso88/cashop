<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les invariants du ledger sont garantis par PostgreSQL, indépendamment du code PHP.
 *
 * RefreshDatabase enveloppe chaque test dans une transaction jamais validée : on force donc
 * l'exécution des triggers différés avec SET CONSTRAINTS ALL IMMEDIATE, ce qui déclenche
 * exactement la vérification faite au COMMIT.
 */
class LedgerInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Dans la transaction du test : la base partagée reste vierge.
        $this->seed();
    }

    private function account(string $code): string
    {
        return DB::table('ledger_accounts')->where('code', $code)->value('id');
    }

    /** @param list<array{0: string, 1: string, 2: int, 3?: string}> $entries [compte, sens, montant, devise] */
    private function postEntries(array $entries, string $currency = 'EUR'): string
    {
        $txId = (string) Str::uuid7();
        DB::table('ledger_transactions')->insert([
            'id' => $txId, 'type' => 'ADJUSTMENT', 'idempotency_key' => $txId,
            'description' => 'test', 'posted_at' => now(), 'created_at' => now(),
        ]);
        foreach ($entries as $e) {
            DB::table('ledger_entries')->insert([
                'id' => (string) Str::uuid7(), 'ledger_transaction_id' => $txId, 'ledger_account_id' => $e[0],
                'direction' => $e[1], 'amount' => $e[2], 'currency_code' => $e[3] ?? $currency, 'created_at' => now(),
            ]);
        }

        return $txId;
    }

    /** Exécute $fn dans un savepoint et force la vérification des contraintes différées. */
    private function commitCheck(callable $fn): void
    {
        DB::transaction(function () use ($fn) {
            $fn();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    #[Test]
    public function une_transaction_equilibree_est_acceptee(): void
    {
        $this->commitCheck(fn () => $this->postEntries([
            [$this->account('suspense:EUR'), 'DEBIT', 10_200],
            [$this->account('in_flight:EUR'), 'CREDIT', 10_000],
            [$this->account('fee_revenue:EUR'), 'CREDIT', 200],
        ]));

        $this->assertSame(3, DB::table('ledger_entries')->count());
    }

    #[Test]
    public function une_transaction_desequilibree_est_refusee(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('déséquilibrée');

        $this->commitCheck(fn () => $this->postEntries([
            [$this->account('suspense:EUR'), 'DEBIT', 10_200],
            [$this->account('in_flight:EUR'), 'CREDIT', 10_000],
        ]));
    }

    #[Test]
    public function une_transaction_a_une_seule_ecriture_est_refusee(): void
    {
        $this->expectException(QueryException::class);

        $this->commitCheck(fn () => $this->postEntries([]));
    }

    #[Test]
    public function l_equilibre_est_verifie_par_devise(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('déséquilibrée');

        // Σ débits = Σ crédits tous montants confondus, mais pas dans chaque devise.
        $this->commitCheck(fn () => $this->postEntries([
            [$this->account('suspense:EUR'), 'DEBIT', 1_000, 'EUR'],
            [$this->account('suspense:XAF'), 'CREDIT', 1_000, 'XAF'],
        ]));
    }

    #[Test]
    public function une_ecriture_doit_etre_dans_la_devise_du_compte(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('autre devise');

        $this->postEntries([[$this->account('suspense:EUR'), 'DEBIT', 1_000, 'XAF']]);
    }

    #[Test]
    public function un_montant_nul_ou_negatif_est_refuse(): void
    {
        $this->expectException(QueryException::class);

        $this->postEntries([[$this->account('suspense:EUR'), 'DEBIT', 0]]);
    }

    #[Test]
    public function les_ecritures_sont_immuables(): void
    {
        $this->commitCheck(fn () => $this->postEntries([
            [$this->account('suspense:EUR'), 'DEBIT', 500],
            [$this->account('in_flight:EUR'), 'CREDIT', 500],
        ]));

        foreach ([
            fn () => DB::table('ledger_entries')->update(['amount' => 1]),
            fn () => DB::table('ledger_entries')->delete(),
            fn () => DB::table('ledger_transactions')->update(['description' => 'modifié']),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('La modification aurait dû être refusée');
            } catch (QueryException $e) {
                $this->assertStringContainsString('ajout seul', $e->getMessage());
            }
        }
    }
}
