<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\OtpCode;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Challenges de second facteur : OTP envoyé (SMS / e-mail) ou code TOTP de l'application.
 *
 * - Le code n'est jamais stocké en clair : HMAC-SHA256(clé applicative, challenge_id | code).
 * - Nombre de tentatives limité par challenge, nombre d'envois limité par destination.
 * - Un code TOTP ne peut servir qu'une fois (anti-rejeu par pas de temps).
 */
class OtpService
{
    public function __construct(private readonly OtpSender $sender, private readonly Totp $totp) {}

    /**
     * @return array{challenge: OtpCode, destination_hint: string|null}
     */
    public function issue(?User $user, OtpChannel $channel, ?string $destination, OtpPurpose $purpose, array $context = []): array
    {
        $destinationHash = self::destinationHash($destination ?? 'totp:'.$user?->id);

        if ($channel !== OtpChannel::Totp) {
            $key = 'otp-send:'.$destinationHash;
            if (RateLimiter::tooManyAttempts($key, config('cashop.otp.max_sends_per_hour'))) {
                throw new ApiException('TOO_MANY_REQUESTS', 'Trop de codes envoyés. Réessayez plus tard.', 429, [], [
                    'Retry-After' => RateLimiter::availableIn($key),
                ]);
            }
            RateLimiter::hit($key, 3600);
        }

        $challenge = new OtpCode([
            'user_id' => $user?->id,
            'channel' => $channel,
            'destination_hash' => $destinationHash,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(config('cashop.otp.ttl_minutes')),
            'context' => $context,
        ]);
        $challenge->id = $challenge->newUniqueId();

        $code = null;
        if ($channel !== OtpChannel::Totp) {
            $code = str_pad((string) random_int(0, 10 ** config('cashop.otp.length') - 1), config('cashop.otp.length'), '0', STR_PAD_LEFT);
            $challenge->code_hash = self::codeHash($challenge->id, $code);
        }
        $challenge->save();

        if ($code !== null) {
            $this->sender->send($channel, $destination, $code, $purpose);
        }

        return ['challenge' => $challenge, 'destination_hint' => self::mask($channel, $destination)];
    }

    /**
     * Valide un challenge et le consomme. Toute erreur renvoie le même message pour ne rien révéler.
     */
    public function verify(string $challengeId, string $code): OtpCode
    {
        $invalid = new ApiException('INVALID_OTP', 'Code invalide ou expiré.', 401);

        return DB::transaction(function () use ($challengeId, $code, $invalid) {
            /** @var OtpCode|null $challenge */
            $challenge = OtpCode::query()->whereKey($challengeId)->lockForUpdate()->first();
            if (! $challenge || ! $challenge->isUsable() || $challenge->attempts >= config('cashop.otp.max_attempts')) {
                throw $invalid;
            }

            $challenge->attempts++;
            $valid = $challenge->channel === OtpChannel::Totp
                ? $this->verifyTotp($challenge, $code)
                : hash_equals($challenge->code_hash, self::codeHash($challenge->id, $code));

            if (! $valid) {
                // On valide la transaction (l'incrément de tentatives est conservé) puis on lève l'erreur.
                $challenge->save();

                return null;
            }

            $challenge->consumed_at = now();
            $challenge->save();

            return $challenge;
        }) ?? throw $invalid;
    }

    /** Vérifie un code TOTP pour un utilisateur, avec protection contre la réutilisation. */
    public function verifyTotpCode(User $user, string $code): bool
    {
        if (! $user->mfa_secret) {
            return false;
        }
        $step = $this->totp->verify($user->mfa_secret, $code);

        // Cache::add est atomique : un même pas de temps n'est accepté qu'une fois.
        return $step !== null && Cache::add("totp-used:{$user->id}:{$step}", true, now()->addMinutes(5));
    }

    private function verifyTotp(OtpCode $challenge, string $code): bool
    {
        $user = $challenge->user;

        return $user !== null && $this->verifyTotpCode($user, $code);
    }

    public static function destinationHash(string $destination): string
    {
        return hash_hmac('sha256', strtolower(trim($destination)), (string) config('app.key'));
    }

    private static function codeHash(string $challengeId, string $code): string
    {
        return hash_hmac('sha256', $challengeId.'|'.$code, (string) config('app.key'));
    }

    private static function mask(OtpChannel $channel, ?string $destination): ?string
    {
        return match ($channel) {
            OtpChannel::Sms => substr($destination, 0, 4).str_repeat('•', max(strlen($destination) - 6, 0)).substr($destination, -2),
            OtpChannel::Email => preg_replace('/^(.).*(@.*)$/', '$1•••$2', $destination),
            OtpChannel::Totp => null,
        };
    }
}
