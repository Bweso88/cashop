<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Services\PinService;
use App\Models\User;
use App\Support\Http\ApiException;
use PHPUnit\Framework\Attributes\Test;

class PinTest extends AuthTestCase
{
    #[Test]
    public function definir_puis_changer_le_pin(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test')];

        $this->putJson('/api/v1/auth/pin', ['pin' => '123456'], $headers)->assertStatus(422)->assertJsonValidationErrors(['pin']);
        $this->putJson('/api/v1/auth/pin', ['pin' => '482915'], $headers)->assertNoContent();
        $this->getJson('/api/v1/profile', $headers)->assertJsonPath('pin_set', true);

        $this->putJson('/api/v1/auth/pin', ['pin' => '730591'], $headers)->assertStatus(422)->assertJsonValidationErrors(['current_pin']);
        $this->putJson('/api/v1/auth/pin', ['pin' => '730591', 'current_pin' => '482915'], $headers)->assertNoContent();

        $user = User::where('email', 'client@cashop.test')->first();
        $this->assertNotSame('730591', $user->transaction_pin_hash);
        $this->assertStringStartsWith('$', $user->transaction_pin_hash);
    }

    #[Test]
    public function le_pin_se_bloque_apres_trop_d_erreurs(): void
    {
        config(['cashop.pin.max_attempts' => 3]);
        $user = User::where('email', 'client@cashop.test')->first();
        $pins = app(PinService::class);
        $pins->set($user, '482915', null);

        $codes = [];
        foreach (['111222', '111222', '111222', '482915'] as $attempt) {
            try {
                $pins->verify($user->fresh(), $attempt);
                $codes[] = 'OK';
            } catch (ApiException $e) {
                $codes[] = $e->errorCode;
            }
        }

        $this->assertSame(['PIN_INVALID', 'PIN_INVALID', 'PIN_LOCKED', 'PIN_LOCKED'], $codes);
    }
}
