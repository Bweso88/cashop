<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('phone_e164', 16)->nullable()->unique();
            $table->string('password');
            // PIN de transaction : Argon2id, distinct du mot de passe.
            $table->string('transaction_pin_hash')->nullable();
            $table->unsignedSmallInteger('failed_pin_count')->default(0);
            $table->string('status', 20)->default(UserStatus::Active->value);
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            // Secret TOTP, chiffré au niveau applicatif.
            $table->text('mfa_secret')->nullable();
            $table->timestampTz('mfa_enabled_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });
        Check::enum('users', 'status', UserStatus::class);

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
