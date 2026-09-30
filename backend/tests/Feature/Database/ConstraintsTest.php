<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConstraintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Dans la transaction du test : la base partagée reste vierge.
        $this->seed();
    }

    private function assertRejected(callable $fn, string $constraint): void
    {
        try {
            DB::transaction($fn);
            $this->fail("La contrainte {$constraint} aurait dû rejeter l'opération");
        } catch (QueryException $e) {
            $this->assertStringContainsString($constraint, $e->getMessage());
        }
    }

    #[Test]
    public function un_statut_hors_enum_est_refuse(): void
    {
        $this->assertRejected(
            fn () => DB::table('users')->where('email', 'client@cashop.test')->update(['status' => 'HACKED']),
            'users_status_check',
        );
    }

    #[Test]
    public function une_capacite_ne_peut_etre_supportee_sans_confirmation(): void
    {
        $this->assertRejected(
            fn () => DB::table('provider_capabilities')
                ->where('confirmation_status', 'NOT_CONFIRMED')
                ->limit(1)
                ->update(['is_supported' => true]),
            'provider_capabilities_supported_requires_confirmation',
        );
    }

    #[Test]
    public function un_solde_de_wallet_ne_peut_pas_etre_negatif(): void
    {
        $this->assertRejected(
            fn () => DB::table('wallet_balances')->limit(1)->update(['available' => -1]),
            'wallet_balances_non_negative',
        );
    }

    #[Test]
    public function un_remboursement_ne_peut_pas_etre_approuve_par_son_demandeur(): void
    {
        $this->assertRejected(function () {
            $user = User::where('email', 'finance@cashop.test')->firstOrFail();
            DB::table('refunds')->insert([
                'id' => (string) Str::uuid7(),
                'transfer_id' => (string) Str::uuid7(),
                'amount' => 100,
                'currency_code' => 'EUR',
                'reason' => 'test',
                'requested_by' => $user->id,
                'approved_by' => $user->id,
                'idempotency_key' => 'test-four-eyes',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'refunds_four_eyes');
    }

    #[Test]
    public function une_cle_d_idempotence_est_unique_par_utilisateur(): void
    {
        $user = User::where('email', 'client@cashop.test')->firstOrFail();
        $row = fn () => [
            'id' => (string) Str::uuid7(), 'key' => 'same-key-123', 'user_id' => $user->id,
            'endpoint' => 'POST /api/v1/transfers', 'request_hash' => str_repeat('a', 64),
            'created_at' => now(), 'expires_at' => now()->addDay(),
        ];
        DB::table('idempotency_keys')->insert($row());

        $this->assertRejected(fn () => DB::table('idempotency_keys')->insert($row()), 'idempotency_keys_user_id_key_unique');
    }

    #[Test]
    public function le_journal_d_audit_est_en_ajout_seul(): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'SYSTEM', 'action' => 'test', 'created_at' => now(), 'hash' => str_repeat('b', 64),
        ]);

        $this->assertRejected(fn () => DB::table('audit_logs')->delete(), 'ajout seul');
    }

    #[Test]
    public function un_meme_webhook_n_est_enregistre_qu_une_fois(): void
    {
        $providerId = DB::table('providers')->where('code', 'mtn_momo')->value('id');
        $row = fn () => [
            'id' => (string) Str::uuid7(), 'provider_id' => $providerId, 'received_at' => now(),
            'headers' => '{}', 'payload' => '{"a":1}', 'payload_sha256' => hash('sha256', '{"a":1}'),
        ];
        DB::table('webhooks')->insert($row());

        $this->assertRejected(fn () => DB::table('webhooks')->insert($row()), 'webhooks_provider_id_payload_sha256_unique');
    }
}
