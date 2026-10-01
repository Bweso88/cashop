<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class RegistrationTest extends AuthTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'Nouveau@Cashop.test',
            'phone' => '+237690123456',
            'password' => 'MotDePasse2026',
            'first_name' => 'Paul',
            'last_name' => 'Mbarga',
            'country' => 'CM',
            'device' => $this->device(),
        ], $overrides);
    }

    #[Test]
    public function inscription_puis_verification_du_telephone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(202)
            ->assertJsonStructure(['challenge_id', 'channel', 'destination_hint', 'expires_at'])
            ->assertJsonPath('channel', 'sms');

        $this->assertSame('+237690123456', $this->sms->sent[0]['destination']);
        $user = User::where('email', 'nouveau@cashop.test')->firstOrFail();
        $this->assertNull($user->phone_verified_at);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame('Paul', $user->profile->first_name);

        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $response->json('challenge_id'), 'code' => $this->sms->lastCode()])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.phone_verified', true)
            ->assertJsonPath('user.kyc_level', 'LEVEL_0')
            ->assertJsonPath('user.kyc_status', 'VERIFIED');

        $this->assertSame(1, $user->devices()->count());
    }

    #[Test]
    public function les_donnees_personnelles_sont_chiffrees_en_base(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(202);

        $raw = \DB::table('user_profiles')->value('first_name');
        $this->assertNotSame('Paul', $raw);
        $this->assertStringNotContainsString('Paul', $raw);
    }

    #[Test]
    public function un_email_existant_ne_se_revele_pas(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'client@cashop.test', 'phone' => '+237690999999']));

        // Même réponse qu'une inscription normale, mais aucun SMS envoyé et le challenge est inutilisable.
        $response->assertStatus(202)->assertJsonStructure(['challenge_id', 'channel', 'destination_hint', 'expires_at']);
        $this->assertSame([], $this->sms->sent);
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $response->json('challenge_id'), 'code' => '123456'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_OTP');
    }

    #[Test]
    public function validation_au_format_cashop(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password' => 'court', 'phone' => '0690', 'country' => 'ZZ']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'errors' => ['password', 'phone', 'country'], 'correlation_id']);
    }
}
