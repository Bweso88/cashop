<?php

namespace App\Domain\Identity\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use App\Support\Http\ApiException;

/**
 * Activation / désactivation de la MFA par application d'authentification (TOTP).
 */
class MfaService
{
    public function __construct(private readonly Totp $totp, private readonly OtpService $otp, private readonly AuditLogger $audit) {}

    /** @return array{secret: string, otpauth_uri: string} */
    public function setup(User $user): array
    {
        if ($user->hasMfaEnabled()) {
            throw new ApiException('MFA_ALREADY_ENABLED', 'La double authentification est déjà activée.', 409);
        }
        $secret = $this->totp->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_enabled_at' => null])->save();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->provisioningUri($secret, $user->email, config('cashop.totp.issuer')),
        ];
    }

    public function confirm(User $user, string $code): void
    {
        if ($user->hasMfaEnabled() || ! $user->mfa_secret) {
            throw new ApiException('MFA_SETUP_REQUIRED', 'Lancez d’abord la configuration.', 409);
        }
        if (! $this->otp->verifyTotpCode($user, $code)) {
            throw new ApiException('INVALID_OTP', 'Code invalide.', 422);
        }
        $user->forceFill(['mfa_enabled_at' => now()])->save();
        $this->audit->log('auth.mfa.enabled', ActorType::User, $user->id, $user);
    }

    public function disable(User $user, string $code): void
    {
        if ($user->isStaff()) {
            throw new ApiException('MFA_MANDATORY', 'La double authentification est obligatoire pour le personnel.', 403);
        }
        if (! $user->hasMfaEnabled() || ! $this->otp->verifyTotpCode($user, $code)) {
            throw new ApiException('INVALID_OTP', 'Code invalide.', 422);
        }
        $user->forceFill(['mfa_secret' => null, 'mfa_enabled_at' => null])->save();
        $this->audit->log('auth.mfa.disabled', ActorType::User, $user->id, $user);
    }
}
