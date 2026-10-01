<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\OtpCode;
use App\Models\User;
use Database\Seeders\Development\DemoUserSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\Test;

class LoginTest extends AuthTestCase
{
    #[Test]
    public function connexion_en_deux_etapes_par_sms(): void
    {
        $this->login('client@cashop.test')
            ->assertStatus(202)
            ->assertJsonPath('channel', 'sms')
            ->assertJsonPath('destination_hint', '+237•••••••01');

        $token = $this->tokenFor('client@cashop.test');

        $this->getJson('/api/v1/profile', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('email', 'client@cashop.test')
            ->assertJsonPath('first_name', 'Amina')
            ->assertJsonPath('last_name', 'Nkoulou')
            ->assertJsonPath('kyc_level', 'LEVEL_1');
    }

    #[Test]
    public function connexion_par_numero_de_telephone(): void
    {
        $this->login('+237690000001')->assertStatus(202)->assertJsonPath('channel', 'sms');
    }

    #[Test]
    public function sans_telephone_le_code_part_par_email(): void
    {
        $this->login('support@cashop.test')->assertStatus(202)->assertJsonPath('channel', 'email');
        $this->assertSame('support@cashop.test', $this->sms->sent[0]['destination']);
    }

    #[Test]
    public function identifiants_incorrects_sans_distinction(): void
    {
        $wrongPassword = $this->login('client@cashop.test', 'mauvais-mot-de-passe')->assertStatus(401)->json();
        $unknownUser = $this->login('inconnu@cashop.test', 'mauvais-mot-de-passe')->assertStatus(401)->json();

        $this->assertSame($wrongPassword['code'], $unknownUser['code']);
        $this->assertSame($wrongPassword['message'], $unknownUser['message']);
    }

    #[Test]
    public function verrouillage_apres_trop_d_echecs(): void
    {
        config(['cashop.auth.max_failed_logins' => 3]);
        // Limiteur de débit HTTP neutralisé : on teste ici le verrouillage du compte.
        $this->withoutMiddleware(ThrottleRequests::class);

        foreach (range(1, 3) as $_) {
            $this->login('client@cashop.test', 'mauvais-mot-de-passe')->assertStatus(401);
        }

        $this->login('client@cashop.test')->assertStatus(423)->assertJsonPath('code', 'ACCOUNT_LOCKED');
        $this->assertTrue(User::where('email', 'client@cashop.test')->first()->isLocked());
    }

    #[Test]
    public function un_code_faux_epuise_les_tentatives(): void
    {
        config(['cashop.otp.max_attempts' => 3]);
        $this->withoutMiddleware(ThrottleRequests::class);
        $challenge = $this->login('client@cashop.test')->json('challenge_id');
        $good = $this->sms->lastCode();
        $bad = $good === '000000' ? '111111' : '000000';

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $bad])->assertStatus(401);
        }
        $this->assertSame(3, OtpCode::find($challenge)->attempts);

        // Même le bon code est refusé une fois les tentatives épuisées.
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $good])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_OTP');
    }

    #[Test]
    public function un_code_ne_sert_qu_une_fois_et_expire(): void
    {
        $challenge = $this->login('client@cashop.test')->json('challenge_id');
        $code = $this->sms->lastCode();

        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $code])->assertOk();
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $code])->assertStatus(401);

        $expired = $this->login('client@cashop.test', deviceId: 'device-2')->json('challenge_id');
        $this->travel(config('cashop.otp.ttl_minutes') + 1)->minutes();
        $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $expired, 'code' => $this->sms->lastCode()])->assertStatus(401);
    }

    #[Test]
    public function le_code_n_est_pas_stocke_en_clair(): void
    {
        $challenge = $this->login('client@cashop.test')->json('challenge_id');

        $this->assertNotSame($this->sms->lastCode(), \DB::table('otp_codes')->where('id', $challenge)->value('code_hash'));
        $this->assertSame(64, strlen(\DB::table('otp_codes')->where('id', $challenge)->value('code_hash')));
    }

    #[Test]
    public function deconnexion_revoque_le_jeton(): void
    {
        $token = $this->tokenFor('client@cashop.test');
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/profile', $headers)->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function une_nouvelle_connexion_remplace_le_jeton_du_meme_appareil(): void
    {
        $this->tokenFor('client@cashop.test');
        $this->tokenFor('client@cashop.test');
        $this->tokenFor('client@cashop.test', 'autre-appareil');

        $user = User::where('email', 'client@cashop.test')->first();
        $this->assertSame(2, $user->tokens()->count());
    }

    #[Test]
    public function le_jeton_expire(): void
    {
        $token = $this->tokenFor('client@cashop.test');
        $this->travel(config('cashop.auth.token_ttl_minutes') + 1)->minutes();

        $this->getJson('/api/v1/profile', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
    }

    #[Test]
    public function limite_de_debit_sur_la_connexion(): void
    {
        $statuses = collect(range(1, 7))->map(fn () => $this->login('client@cashop.test', DemoUserSeeder::DEMO_PASSWORD.'x')->status());

        $this->assertContains(429, $statuses->all());
    }
}
