<?php

use App\Domain\Audit\Enums\ActorType;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Payments\Enums\IdempotencyStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('type', 60);
            $table->string('title');
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->string('status', 10)->default(NotificationStatus::Pending->value);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });
        Check::enum('notifications', 'channel', NotificationChannel::class);
        Check::enum('notifications', 'status', NotificationStatus::class);

        // Journal d'audit : ajout seul, chaîné par hachage pour détecter toute altération.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('actor_type', 10);
            $table->uuid('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->jsonb('changes')->nullable(); // champs sensibles masqués
            $table->timestampTz('created_at');
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
        });
        Check::enum('audit_logs', 'actor_type', ActorType::class);
        Check::appendOnly('audit_logs');

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 120);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response')->nullable();
            $table->string('status', 12)->default(IdempotencyStatus::Processing->value);
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->index();

            $table->unique(['user_id', 'key']);
        });
        Check::enum('idempotency_keys', 'status', IdempotencyStatus::class);
        Check::add('idempotency_keys', 'idempotency_keys_completed_has_response',
            "status <> 'COMPLETED' OR (response IS NOT NULL AND response_status IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
