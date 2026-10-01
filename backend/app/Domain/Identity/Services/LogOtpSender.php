<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Développement uniquement : écrit le code dans les logs locaux. Refuse de fonctionner en production.
 */
class LogOtpSender implements OtpSender
{
    public function send(OtpChannel $channel, string $destination, string $code, OtpPurpose $purpose): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LogOtpSender est interdit en production : configurez SMS_PROVIDER.');
        }

        Log::info("[DEV] OTP {$purpose->value} via {$channel->value}", ['destination' => $destination, 'code' => $code]);
    }
}
