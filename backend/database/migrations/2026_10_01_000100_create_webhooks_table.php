<?php

use App\Domain\Webhooks\Enums\WebhookStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary(); // event ID Cashop
            $table->foreignUuid('provider_id')->constrained();
            $table->string('provider_event_id')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('provider_timestamp')->nullable();
            $table->jsonb('headers');   // filtrés : jamais d'en-tête d'authentification
            $table->jsonb('payload');
            $table->char('payload_sha256', 64);
            $table->string('status', 10)->default(WebhookStatus::Received->value);
            $table->foreignUuid('transfer_id')->nullable()->constrained();
            $table->timestampTz('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->string('source_ip', 45)->nullable();

            // Anti-rejeu : un même événement ou un même payload n'est traité qu'une fois.
            $table->unique(['provider_id', 'payload_sha256']);
            $table->index(['status', 'received_at']);
        });
        Check::enum('webhooks', 'status', WebhookStatus::class);
        DB::statement(
            'CREATE UNIQUE INDEX webhooks_provider_event_unique ON webhooks (provider_id, provider_event_id) WHERE provider_event_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
