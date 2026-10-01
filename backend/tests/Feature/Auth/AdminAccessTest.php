<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class AdminAccessTest extends AuthTestCase
{
    private function staffToken(string $email, bool $withMfa): string
    {
        if (! $withMfa) {
            return $this->tokenFor($email);
        }
        $secret = $this->enableTotp(User::where('email', $email)->first());
        $challenge = $this->login($email)->json('challenge_id');

        return $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $this->totpCode($secret)])->json('token');
    }

    #[Test]
    public function un_client_n_accede_pas_au_back_office(): void
    {
        $this->getJson('/api/admin/v1/me', ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test')])
            ->assertStatus(403)->assertJsonPath('code', 'STAFF_ONLY');
    }

    #[Test]
    public function le_personnel_sans_mfa_doit_l_activer(): void
    {
        $this->getJson('/api/admin/v1/me', ['Authorization' => 'Bearer '.$this->staffToken('support@cashop.test', false)])
            ->assertStatus(403)->assertJsonPath('code', 'MFA_REQUIRED');
    }

    #[Test]
    public function le_personnel_avec_mfa_voit_ses_permissions(): void
    {
        $response = $this->getJson('/api/admin/v1/me', ['Authorization' => 'Bearer '.$this->staffToken('compliance@cashop.test', true)])
            ->assertOk()
            ->assertJsonPath('roles', ['compliance']);

        $this->assertContains('kyc.review', $response->json('permissions'));
        $this->assertNotContains('refunds.approve', $response->json('permissions'));
    }

    #[Test]
    public function le_jeton_du_personnel_est_court(): void
    {
        $token = $this->staffToken('admin@cashop.test', true);
        $this->travel(config('cashop.auth.staff_token_ttl_minutes') + 1)->minutes();

        $this->getJson('/api/admin/v1/me', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }

    #[Test]
    public function sans_jeton_401_au_format_cashop(): void
    {
        $this->getJson('/api/admin/v1/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
