<?php

namespace Tests\Support;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;

/** Capture les codes envoyés pour que les tests puissent les saisir. */
class FakeOtpSender implements OtpSender
{
    /** @var list<array{channel: OtpChannel, destination: string, code: string, purpose: OtpPurpose}> */
    public array $sent = [];

    public function send(OtpChannel $channel, string $destination, string $code, OtpPurpose $purpose): void
    {
        $this->sent[] = compact('channel', 'destination', 'code', 'purpose');
    }

    public function lastCode(): string
    {
        return end($this->sent)['code'];
    }
}
