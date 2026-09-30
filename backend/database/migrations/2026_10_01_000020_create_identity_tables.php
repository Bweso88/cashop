<?php

use App\Domain\Identity\Enums\DevicePlatform;
use App\Domain\Identity\Enums\OtpChannel;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Champs personnels chiffrés au niveau applicatif (casts "encrypted") : type text.
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->char('nationality', 2)->nullable();
            $table->char('country_of_residence', 2)->nullable();
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            $table->text('city')->nullable();
            $table->text('postal_code')->nullable();
            $table->char('preferred_currency', 3)->nullable();
            $table->string('locale', 10)->default('fr');
            $table->timestampsTz();

            $table->foreign('nationality')->references('code')->on('countries');
            $table->foreign('country_of_residence')->references('code')->on('countries');
            $table->foreign('preferred_currency')->references('code')->on('currencies');
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('platform', 10);
            $table->string('name')->nullable();
            // Clé publique liée à l'appareil (signature biométrique des challenges).
            $table->text('public_key')->nullable();
            $table->text('push_token')->nullable();
            $table->timestampTz('trusted_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'device_id']);
        });
        Check::enum('user_devices', 'platform', DevicePlatform::class);

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            // HMAC de la destination (téléphone/e-mail) : pas de donnée personnelle en clair.
            $table->string('destination_hash', 64)->index();
            $table->string('purpose', 20);
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('created_at');
        });
        Check::enum('otp_codes', 'channel', OtpChannel::class);
        Check::enum('otp_codes', 'purpose', OtpPurpose::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_profiles');
    }
};
