<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Hash;

/**
 * PIN de transaction : distinct du mot de passe, haché (Argon2id), verrouillé après N échecs.
 */
class PinService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function set(User $user, string $pin, ?string $currentPin): void
    {
        $hadPin = $user->hasPin();
        if ($hadPin) {
            if ($currentPin === null) {
                throw new ApiException('VALIDATION_ERROR', 'Le PIN actuel est requis.', 422, ['current_pin' => ['Le PIN actuel est requis.']]);
            }
            $this->verify($user, $currentPin);
        }
        if (self::isTrivial($pin)) {
            throw new ApiException('VALIDATION_ERROR', 'Ce PIN est trop facile à deviner.', 422, ['pin' => ['Évitez les suites et les chiffres répétés.']]);
        }

        $user->forceFill(['transaction_pin_hash' => Hash::make($pin), 'failed_pin_count' => 0])->save();
        $this->audit->log($hadPin ? 'auth.pin.changed' : 'auth.pin.set', ActorType::User, $user->id, $user);
    }

    /** Vérifie le PIN ; lève PIN_INVALID ou PIN_LOCKED. */
    public function verify(User $user, string $pin): void
    {
        $max = config('cashop.pin.max_attempts');

        if (! $user->hasPin()) {
            throw new ApiException('PIN_NOT_SET', 'Définissez d’abord votre PIN de transaction.', 403);
        }
        if ($user->failed_pin_count >= $max) {
            throw new ApiException('PIN_LOCKED', 'PIN bloqué après trop d’essais. Réinitialisez-le.', 423);
        }

        if (Hash::check($pin, $user->transaction_pin_hash)) {
            if ($user->failed_pin_count > 0) {
                $user->forceFill(['failed_pin_count' => 0])->save();
            }

            return;
        }

        // Incrément atomique en base (UPDATE ... SET failed_pin_count = failed_pin_count + 1).
        $user->increment('failed_pin_count');
        $count = $user->failed_pin_count;
        $this->audit->log('auth.pin.failed', ActorType::User, $user->id, $user, ['attempt' => $count]);

        throw $count >= $max
            ? new ApiException('PIN_LOCKED', 'PIN bloqué après trop d’essais. Réinitialisez-le.', 423)
            : new ApiException('PIN_INVALID', 'PIN incorrect.', 401);
    }

    /** Refuse les PIN répétés (000000) et les suites (123456, 654321). */
    public static function isTrivial(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }
        $digits = array_map('intval', str_split($pin));
        $up = $down = true;
        for ($i = 1; $i < count($digits); $i++) {
            $up = $up && $digits[$i] === ($digits[$i - 1] + 1) % 10;
            $down = $down && $digits[$i] === ($digits[$i - 1] + 9) % 10;
        }

        return $up || $down;
    }
}
