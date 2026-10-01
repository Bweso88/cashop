<?php

namespace App\Domain\Identity\Services;

/**
 * TOTP (RFC 6238) sur HOTP (RFC 4226), HMAC-SHA1 — l'algorithme pris en charge par toutes les
 * applications d'authentification (Google Authenticator, Microsoft Authenticator, 1Password…).
 */
final class Totp
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly int $window = 1,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('cashop.totp.period'), config('cashop.totp.digits'), config('cashop.totp.window'));
    }

    /** Secret aléatoire de 160 bits, encodé en base32. */
    public function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public function code(string $base32Secret, ?int $timestamp = null): string
    {
        return $this->hotp(self::base32Decode($base32Secret), $this->step($timestamp ?? time()));
    }

    /**
     * Vérifie un code dans la fenêtre de tolérance. Renvoie le pas de temps reconnu (pour
     * empêcher la réutilisation du même code) ou null.
     */
    public function verify(string $base32Secret, string $code, ?int $timestamp = null): ?int
    {
        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return null;
        }
        $key = self::base32Decode($base32Secret);
        $current = $this->step($timestamp ?? time());

        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            if (hash_equals($this->hotp($key, $current + $offset), $code)) {
                return $current + $offset;
            }
        }

        return null;
    }

    public function provisioningUri(string $base32Secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($account),
            http_build_query(['secret' => $base32Secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => $this->digits, 'period' => $this->period], '', '&', PHP_QUERY_RFC3986),
        );
    }

    /** HOTP avec une clé binaire brute (RFC 4226 §5.3). */
    public function hotp(string $key, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    private function step(int $timestamp): int
    {
        return intdiv($timestamp, $this->period);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim(str_replace(' ', '', $base32), '='));
        $bits = '';
        foreach (str_split($base32) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) {
                throw new \InvalidArgumentException('Secret base32 invalide');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
