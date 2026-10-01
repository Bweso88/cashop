<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;

/**
 * Envoi d'un code OTP (SMS ou e-mail). Le fournisseur SMS est à choisir (NOT CONFIRMED) :
 * une implémentation par fournisseur sera ajoutée derrière cette interface.
 */
interface OtpSender
{
    public function send(OtpChannel $channel, string $destination, string $code, OtpPurpose $purpose): void;
}
