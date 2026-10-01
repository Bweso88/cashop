<?php

namespace Tests\Feature\Auth;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class AuditAndHeadersTest extends AuthTestCase
{
    #[Test]
    public function chaque_etape_de_connexion_est_auditee_dans_une_chaine_intacte(): void
    {
        $this->login('client@cashop.test', 'mauvais-mot-de-passe');
        $this->tokenFor('client@cashop.test');

        $actions = DB::table('audit_logs')->orderBy('id')->pluck('action')->all();
        $this->assertContains('auth.login.failed', $actions);
        $this->assertContains('auth.login.password_ok', $actions);
        $this->assertContains('auth.login.succeeded', $actions);
        $this->assertNull(AuditLogger::verifyChain());
    }

    #[Test]
    public function une_alteration_du_journal_est_detectee(): void
    {
        $this->tokenFor('client@cashop.test');
        $id = DB::table('audit_logs')->orderBy('id')->value('id');

        // Contournement volontaire du trigger "ajout seul" pour simuler une altération en base.
        DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_append_only');
        DB::table('audit_logs')->where('id', $id)->update(['action' => 'falsifié']);
        DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');

        $this->assertSame($id, AuditLogger::verifyChain());
    }

    #[Test]
    public function aucun_secret_dans_le_journal(): void
    {
        $this->tokenFor('client@cashop.test');
        $code = $this->sms->lastCode();

        $dump = DB::table('audit_logs')->get()->toJson();
        $this->assertStringNotContainsString($code, $dump);
        $this->assertStringNotContainsString('Cashop-Demo-2026!', $dump);
    }

    #[Test]
    public function les_champs_sensibles_sont_masques(): void
    {
        app(AuditLogger::class)->log('test.masquage', ActorType::System, changes: [
            'pin' => '482915', 'montant' => 100, 'carte' => ['pan' => '4111111111111111', 'token' => 'tok_secret'],
        ]);

        $changes = json_decode(DB::table('audit_logs')->where('action', 'test.masquage')->value('changes'), true);
        $this->assertSame(['pin' => '[MASQUÉ]', 'carte' => ['pan' => '[MASQUÉ]', 'token' => '[MASQUÉ]'], 'montant' => 100], $changes);
        $this->assertNull(AuditLogger::verifyChain());
    }

    #[Test]
    public function correlation_id_et_en_tetes_de_securite(): void
    {
        $id = (string) Str::uuid7();
        $this->getJson('/api/v1/profile', ['X-Correlation-Id' => $id])
            ->assertStatus(401)
            ->assertHeader('X-Correlation-Id', $id)
            ->assertJsonPath('correlation_id', $id)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        // Un identifiant mal formé est remplacé.
        $this->getJson('/api/v1/profile', ['X-Correlation-Id' => '<script>'])
            ->assertHeader('X-Correlation-Id');
        $this->assertTrue(Str::isUuid($this->getJson('/api/v1/profile', ['X-Correlation-Id' => 'x'])->headers->get('X-Correlation-Id')));
    }

    #[Test]
    public function route_inconnue_au_format_cashop(): void
    {
        $this->getJson('/api/v1/inexistant')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
    }
}
