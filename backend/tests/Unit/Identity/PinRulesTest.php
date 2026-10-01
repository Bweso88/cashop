<?php

namespace Tests\Unit\Identity;

use App\Domain\Identity\Services\PinService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PinRulesTest extends TestCase
{
    #[Test]
    public function refuse_les_pin_triviaux(): void
    {
        foreach (['000000', '111111', '123456', '654321', '890123', '210987'] as $pin) {
            $this->assertTrue(PinService::isTrivial($pin), $pin);
        }
    }

    #[Test]
    public function accepte_les_pin_non_triviaux(): void
    {
        foreach (['482915', '135790', '112233', '908172'] as $pin) {
            $this->assertFalse(PinService::isTrivial($pin), $pin);
        }
    }
}
