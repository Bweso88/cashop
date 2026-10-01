<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Services\Totp;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class MfaTest extends AuthTestCase
{
    #[Test]
    public function activation_puis_connexion_par_totp(): void
    {
        $token = $this->tokenFor('client@cashop.test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $setup = $this->postJson('/api/v1/auth/mfa/totp', [], $headers)->assertOk()->json();
        $this->assertStringStartsWith('otpauth://totp/Cashop:', $setup['otpauth_uri']);

        $this->postJson('/api/v1/auth/mfa/totp/confirm', ['code' => app(Totp::class)->code($setup['secret'])], $headers)->assertNoContent();
        $user = User::where('email', 'client@cashop.test')->first();
        $this->assertTrue($user->hasMfaEnabled());
        $this->assertNotSame($setup['secret'], \DB::table('users')->where('id', $user->id)->value('mfa_secret'), 'Secret chiffré en base');

        // La connexion suivante exige le TOTP (pas de SMS envoyé).
        $sent = count($this->sms->sent);
        $challenge = $this->login('client@cashop.test', deviceId: 'device-2')->assertStatus(202)->assertJsonPath('channel', 'totp')->json('challenge_id');
        $this->assertCount($sent, $this->sms->sent);

        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $this->totpCode($setup['secret'], 1)])
            ->assertOk()->assertJsonPath('user.mfa_enabled', true);
    }

    #[Test]
    public function un_code_totp_ne_peut_pas_etre_rejoue(): void
    {
        $user = User::where('email', 'client@cashop.test')->first();
        $secret = $this->enableTotp($user);
        $code = $this->totpCode($secret);

        $first = $this->login('client@cashop.test')->json('challenge_id');
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $first, 'code' => $code])->assertOk();

        $second = $this->login('client@cashop.test', deviceId: 'device-2')->json('challenge_id');
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $second, 'code' => $code])->assertStatus(401);
    }

    #[Test]
    public function le_personnel_ne_peut_pas_desactiver_la_mfa(): void
    {
        $admin = User::where('email', 'admin@cashop.test')->first();
        $secret = $this->enableTotp($admin);
        $challenge = $this->login('admin@cashop.test')->json('challenge_id');
        $token = $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $this->totpCode($secret)])->json('token');

        $this->deleteJson('/api/v1/auth/mfa/totp', ['code' => $this->totpCode($secret, 1)], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403)->assertJsonPath('code', 'MFA_MANDATORY');
    }
}
