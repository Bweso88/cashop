<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\TokenIssuer;
use App\Domain\Kyc\Enums\KycStatus;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * Seconde étape : valide le challenge (inscription ou connexion) et émet un jeton pour l'appareil.
 */
class CompleteChallenge
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly TokenIssuer $tokens,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{user: User, token: NewAccessToken} */
    public function __invoke(string $challengeId, string $code, ?string $ip): array
    {
        $challenge = $this->otp->verify($challengeId, $code);
        if (! in_array($challenge->purpose, [OtpPurpose::Register, OtpPurpose::Login], true) || ! $challenge->user) {
            throw new ApiException('INVALID_OTP', 'Code invalide ou expiré.', 401);
        }

        $user = $challenge->user;
        if ($user->isLocked()) {
            throw new ApiException('ACCOUNT_LOCKED', 'Compte temporairement verrouillé.', 423);
        }
        $device = $challenge->context ?? [];

        $token = DB::transaction(function () use ($user, $challenge, $device, $ip) {
            // Un OTP SMS valide prouve la possession du téléphone : niveau KYC 0 atteint.
            if ($challenge->channel === OtpChannel::Sms && $user->phone_verified_at === null) {
                $user->phone_verified_at = now();
                $user->kycProfile()->where('level_code', 'LEVEL_0')->where('status', KycStatus::Pending)
                    ->update(['status' => KycStatus::Verified, 'verified_at' => now()]);
            }
            if ($challenge->channel === OtpChannel::Email && $user->email_verified_at === null) {
                $user->email_verified_at = now();
            }
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

            $user->devices()->updateOrCreate(
                ['device_id' => $device['device_id']],
                ['platform' => $device['platform'], 'name' => $device['name'] ?? null, 'trusted_at' => now(), 'last_seen_at' => now(), 'revoked_at' => null],
            );

            return $this->tokens->issue($user, $device['device_id']);
        });

        $this->audit->log(
            $challenge->purpose === OtpPurpose::Register ? 'auth.register.verified' : 'auth.login.succeeded',
            ActorType::User, $user->id, $user, ['channel' => $challenge->channel->value, 'device_id' => $device['device_id'], 'ip' => $ip],
        );

        return ['user' => $user->fresh(), 'token' => $token];
    }
}
