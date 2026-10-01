<?php

namespace Tests\Unit\Identity;

use App\Domain\Identity\Services\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** Vecteurs de test officiels de la RFC 6238 (annexe B), HMAC-SHA1, 8 chiffres. */
    public static function rfc6238Vectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    #[Test]
    #[DataProvider('rfc6238Vectors')]
    public function respecte_les_vecteurs_de_la_rfc_6238(int $time, string $expected): void
    {
        $totp = new Totp(period: 30, digits: 8, window: 0);
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame($expected, $totp->code($secret, $time));
    }

    #[Test]
    public function tolere_un_pas_de_derive_mais_pas_deux(): void
    {
        $totp = new Totp(window: 1);
        $secret = $totp->generateSecret();
        $now = 1_800_000_000;

        $this->assertNotNull($totp->verify($secret, $totp->code($secret, $now - 30), $now));
        $this->assertNotNull($totp->verify($secret, $totp->code($secret, $now + 30), $now));
        $this->assertNull($totp->verify($secret, $totp->code($secret, $now - 90), $now));
        $this->assertNull($totp->verify($secret, 'abcdef', $now));
    }

    #[Test]
    public function base32_aller_retour(): void
    {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
        $this->assertSame(32, strlen((new Totp)->generateSecret()));
    }

    #[Test]
    public function genere_une_uri_otpauth(): void
    {
        $uri = (new Totp)->provisioningUri('JBSWY3DPEHPK3PXP', 'jane@cashop.test', 'Cashop');

        $this->assertStringStartsWith('otpauth://totp/Cashop:jane%40cashop.test?secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=Cashop', $uri);
    }
}
