<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\Totp;
use App\Models\User;
use Database\Seeders\Development\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeOtpSender;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeOtpSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
        $this->sms = new FakeOtpSender;
        $this->app->instance(OtpSender::class, $this->sms);
    }

    protected function device(string $id = 'device-test-1'): array
    {
        return ['device_id' => $id, 'platform' => 'android', 'name' => 'Pixel test'];
    }

    protected function login(string $login, string $password = DemoUserSeeder::DEMO_PASSWORD, string $deviceId = 'device-test-1'): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password, 'device' => $this->device($deviceId)]);
    }

    /** Connexion complète (mot de passe + OTP SMS/e-mail) ; renvoie le jeton. */
    protected function tokenFor(string $email, string $deviceId = 'device-test-1'): string
    {
        $challenge = $this->login($email, deviceId: $deviceId)->assertStatus(202)->json('challenge_id');

        return $this->postJson('/api/v1/auth/verify-otp', ['challenge_id' => $challenge, 'code' => $this->sms->lastCode()])
            ->assertOk()->json('token');
    }

    /** Active la MFA TOTP pour un utilisateur et renvoie son secret. */
    protected function enableTotp(User $user): string
    {
        $secret = app(Totp::class)->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => now()])->save();

        return $secret;
    }

    /** Code TOTP valide non encore utilisé (pas de temps futur pour éviter l'anti-rejeu). */
    protected function totpCode(string $secret, int $stepOffset = 0): string
    {
        return app(Totp::class)->code($secret, time() + 30 * $stepOffset);
    }

    protected function clearRateLimits(): void
    {
        RateLimiter::clear('');
        Cache::flush();
    }
}
