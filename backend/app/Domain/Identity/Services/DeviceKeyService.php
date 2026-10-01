<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\UserDevice;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Authentification biométrique liée à l'appareil (docs/SECURITY.md §1).
 *
 * L'app génère une paire de clés dans le Secure Enclave / Android Keystore, protégée par la
 * biométrie, et enregistre la clé publique. Pour confirmer une opération, le serveur émet un
 * challenge à usage unique que l'app signe. La biométrie ne quitte jamais l'appareil.
 */
class DeviceKeyService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function registerKey(User $user, string $deviceId, string $publicKeyPem): UserDevice
    {
        $key = openssl_pkey_get_public($publicKeyPem);
        $details = $key ? openssl_pkey_get_details($key) : false;
        $acceptable = $details && (
            ($details['type'] === OPENSSL_KEYTYPE_EC && ($details['ec']['curve_name'] ?? null) === 'prime256v1')
            || ($details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 2048)
        );
        if (! $acceptable) {
            throw new ApiException('VALIDATION_ERROR', 'Clé publique invalide (EC P-256 ou RSA ≥ 2048 attendue).', 422, [
                'public_key' => ['Clé publique invalide.'],
            ]);
        }

        $device = $this->device($user, $deviceId);
        $device->forceFill(['public_key' => $details['key']])->save();
        $this->audit->log('auth.device.key_registered', ActorType::User, $user->id, $device);

        return $device;
    }

    /** @return array{challenge_id: string, nonce: string, expires_at: string} */
    public function issueChallenge(User $user, string $deviceId): array
    {
        $device = $this->device($user, $deviceId);
        if (! $device->public_key) {
            throw new ApiException('DEVICE_KEY_MISSING', 'Aucune clé biométrique enregistrée pour cet appareil.', 409);
        }

        $id = (string) Str::uuid7();
        $nonce = base64_encode(random_bytes(32));
        $ttl = config('cashop.device.challenge_ttl_seconds');
        Cache::put("device-challenge:{$id}", ['user_id' => $user->id, 'device_id' => $deviceId, 'nonce' => $nonce], $ttl);

        return ['challenge_id' => $id, 'nonce' => $nonce, 'expires_at' => now()->addSeconds($ttl)->toIso8601String()];
    }

    /** Vérifie la signature (base64) du nonce. Le challenge est consommé dans tous les cas. */
    public function verify(User $user, string $deviceId, string $challengeId, string $signature): bool
    {
        $challenge = Cache::pull("device-challenge:{$challengeId}");
        if (! $challenge || $challenge['user_id'] !== $user->id || $challenge['device_id'] !== $deviceId) {
            return false;
        }
        $device = $user->devices()->where('device_id', $deviceId)->whereNull('revoked_at')->first();
        $raw = base64_decode($signature, true);
        if (! $device?->public_key || $raw === false) {
            return false;
        }

        return openssl_verify($challenge['nonce'], $raw, $device->public_key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function device(User $user, string $deviceId): UserDevice
    {
        $device = $user->devices()->where('device_id', $deviceId)->whereNull('revoked_at')->first();
        if (! $device) {
            throw new ApiException('DEVICE_NOT_FOUND', 'Appareil inconnu ou révoqué.', 404);
        }

        return $device;
    }
}
