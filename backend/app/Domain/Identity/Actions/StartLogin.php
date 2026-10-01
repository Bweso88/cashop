<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\DTO\DeviceContext;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Services\OtpService;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Hash;

/**
 * Première étape de connexion : vérifie le mot de passe puis exige un second facteur
 * (TOTP si la MFA est activée, sinon OTP par SMS, ou par e-mail à défaut de téléphone vérifié).
 */
class StartLogin
{
    /** Hash factice (même algorithme que les vrais) : le temps de réponse ne révèle pas si le compte existe. */
    private static ?string $dummyHash = null;

    public function __construct(private readonly OtpService $otp, private readonly AuditLogger $audit) {}

    /**
     * @return array{challenge_id: string, channel: string, destination_hint: string|null, expires_at: string}
     */
    public function __invoke(string $login, string $password, DeviceContext $device): array
    {
        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'phone_e164', str_contains($login, '@') ? strtolower($login) : $login)
            ->first();

        $invalid = new ApiException('INVALID_CREDENTIALS', 'Identifiants incorrects.', 401);

        if (! $user) {
            Hash::check($password, self::$dummyHash ??= Hash::make(random_bytes(16)));
            throw $invalid;
        }
        if ($user->isLocked()) {
            $this->audit->log('auth.login.blocked', ActorType::User, $user->id, $user, ['ip' => $device->ip]);
            throw new ApiException('ACCOUNT_LOCKED', 'Compte temporairement verrouillé. Réessayez plus tard ou contactez le support.', 423);
        }
        if (! Hash::check($password, $user->password)) {
            $this->recordFailure($user, $device);
            throw $invalid;
        }

        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();

        [$channel, $destination] = match (true) {
            $user->hasMfaEnabled() => [OtpChannel::Totp, null],
            $user->phone_e164 !== null => [OtpChannel::Sms, $user->phone_e164],
            default => [OtpChannel::Email, $user->email],
        };

        $issued = $this->otp->issue($user, $channel, $destination, OtpPurpose::Login, $device->toArray());
        $this->audit->log('auth.login.password_ok', ActorType::User, $user->id, $user, ['channel' => $channel->value, 'ip' => $device->ip]);

        return [
            'challenge_id' => $issued['challenge']->id,
            'channel' => $channel->value,
            'destination_hint' => $issued['destination_hint'],
            'expires_at' => $issued['challenge']->expires_at->toIso8601String(),
        ];
    }

    private function recordFailure(User $user, DeviceContext $device): void
    {
        $user->increment('failed_login_count');
        if ($user->failed_login_count >= config('cashop.auth.max_failed_logins')) {
            $user->forceFill([
                'locked_until' => now()->addMinutes(config('cashop.auth.lockout_minutes')),
                'failed_login_count' => 0,
            ])->save();
            $this->audit->log('auth.login.locked', ActorType::User, $user->id, $user, ['ip' => $device->ip]);

            return;
        }
        $this->audit->log('auth.login.failed', ActorType::User, $user->id, $user, ['ip' => $device->ip]);
    }
}
