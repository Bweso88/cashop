<?php

use App\Domain\Payments\Enums\DisputeStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency_code', 3);
            $table->text('reason');
            $table->string('status', 12)->default(RefundStatus::Requested->value);
            $table->foreignUuid('requested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->string('provider_reference')->nullable();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained();
            $table->string('idempotency_key', 100);
            $table->timestampsTz();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['transfer_id', 'idempotency_key']);
        });
        Check::enum('refunds', 'status', RefundStatus::class);
        Check::add('refunds', 'refunds_amount_positive', 'amount > 0');
        // Principe des quatre yeux : l'approbateur n'est jamais le demandeur.
        Check::add('refunds', 'refunds_four_eyes', 'approved_by IS NULL OR approved_by <> requested_by');

        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('opened_by')->constrained('users');
            $table->text('reason');
            $table->string('status', 20)->default(DisputeStatus::Open->value);
            $table->foreignUuid('assigned_to')->nullable()->constrained('users');
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });
        Check::enum('disputes', 'status', DisputeStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('refunds');
    }
};
