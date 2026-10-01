<?php

use App\Domain\Identity\Enums\OtpChannel;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 : un challenge de connexion peut être validé par un code TOTP (pas de code stocké)
 * et porte le contexte de la tentative (appareil, IP) jusqu'à l'émission du jeton.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('code_hash')->nullable()->change();
            $table->jsonb('context')->nullable();
        });
        DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_channel_check');
        Check::enum('otp_codes', 'channel', OtpChannel::class);
        Check::add('otp_codes', 'otp_codes_code_required',
            "channel = 'totp' OR code_hash IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_code_required');
        DB::table('otp_codes')->where('channel', 'totp')->delete();
        DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_channel_check');
        DB::statement("ALTER TABLE otp_codes ADD CONSTRAINT otp_codes_channel_check CHECK (channel IN ('sms', 'email'))");
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn('context');
            $table->string('code_hash')->nullable(false)->change();
        });
    }
};
