<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Services\DeviceKeyService;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

class DeviceKeyTest extends AuthTestCase
{
    #[Test]
    public function signature_biometrique_d_un_challenge(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test', 'iphone-1')];

        // Clé générée "dans l'appareil" (Secure Enclave simulé) : EC P-256.
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $publicPem = openssl_pkey_get_details($key)['key'];

        $this->postJson('/api/v1/auth/device/key', ['public_key' => $publicPem], $headers)->assertNoContent();
        $challenge = $this->postJson('/api/v1/auth/device/challenge', [], $headers)->assertOk()->json();

        openssl_sign($challenge['nonce'], $signature, $key, OPENSSL_ALGO_SHA256);
        $user = User::where('email', 'client@cashop.test')->first();
        $service = app(DeviceKeyService::class);

        $this->assertTrue($service->verify($user, 'iphone-1', $challenge['challenge_id'], base64_encode($signature)));
        // Usage unique : la même signature est refusée ensuite.
        $this->assertFalse($service->verify($user, 'iphone-1', $challenge['challenge_id'], base64_encode($signature)));
    }

    #[Test]
    public function une_signature_d_une_autre_cle_est_refusee(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test', 'iphone-1')];
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $attacker = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->postJson('/api/v1/auth/device/key', ['public_key' => openssl_pkey_get_details($key)['key']], $headers)->assertNoContent();

        $challenge = $this->postJson('/api/v1/auth/device/challenge', [], $headers)->json();
        openssl_sign($challenge['nonce'], $signature, $attacker, OPENSSL_ALGO_SHA256);

        $user = User::where('email', 'client@cashop.test')->first();
        $this->assertFalse(app(DeviceKeyService::class)->verify($user, 'iphone-1', $challenge['challenge_id'], base64_encode($signature)));
    }

    #[Test]
    public function une_cle_faible_est_refusee(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test')];
        $weak = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 1024]);

        $this->postJson('/api/v1/auth/device/key', ['public_key' => openssl_pkey_get_details($weak)['key']], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
