<?php

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\QuoteStatus;
use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Providers\Enums\ProviderOperation;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->string('nickname')->nullable();
            $table->char('country_code', 2);
            $table->string('payout_method', 20);
            // Coordonnées chiffrées (MSISDN, jeton de carte, IBAN…). Jamais de PAN en clair.
            $table->text('details');
            $table->string('details_hash', 64);
            $table->string('masked_details', 40);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('country_code')->references('code')->on('countries');
            $table->unique(['user_id', 'details_hash']);
        });
        Check::enum('beneficiaries', 'payout_method', PayoutMethod::class);

        Schema::create('transfer_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained();
            $table->bigInteger('send_amount');
            $table->char('send_currency', 3);
            $table->bigInteger('receive_amount');
            $table->char('receive_currency', 3);
            $table->decimal('fx_rate', 24, 12);
            $table->string('fx_rate_source', 40);
            $table->jsonb('fee_breakdown');
            $table->bigInteger('total_fees');
            $table->bigInteger('total_debit');
            $table->string('payment_method', 20);
            $table->string('payout_method', 20);
            $table->char('destination_country', 2);
            $table->string('provider_quote_reference')->nullable();
            $table->string('status', 10)->default(QuoteStatus::Active->value);
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('send_currency')->references('code')->on('currencies');
            $table->foreign('receive_currency')->references('code')->on('currencies');
            $table->foreign('destination_country')->references('code')->on('countries');
            $table->index(['user_id', 'created_at']);
        });
        Check::enum('transfer_quotes', 'status', QuoteStatus::class);
        Check::enum('transfer_quotes', 'payment_method', PaymentMethod::class);
        Check::enum('transfer_quotes', 'payout_method', PayoutMethod::class);
        Check::add('transfer_quotes', 'transfer_quotes_amounts_check',
            'send_amount > 0 AND receive_amount > 0 AND total_fees >= 0 AND total_debit = send_amount + total_fees AND fx_rate > 0');

        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary(); // = transaction_id exposé
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('quote_id')->unique()->constrained('transfer_quotes')->restrictOnDelete();
            $table->foreignUuid('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->string('status', 25);
            // Montants et taux figés à la confirmation.
            $table->bigInteger('send_amount');
            $table->char('send_currency', 3);
            $table->bigInteger('receive_amount');
            $table->char('receive_currency', 3);
            $table->decimal('fx_rate', 24, 12);
            $table->bigInteger('total_fees');
            $table->bigInteger('total_debit');
            $table->string('payment_method', 20);
            $table->string('payout_method', 20);
            $table->char('destination_country', 2);
            $table->string('purpose', 40)->nullable();
            $table->string('note', 140)->nullable();
            // Traçabilité
            $table->uuid('correlation_id')->index();
            $table->string('external_reference', 64)->unique();
            $table->string('provider_reference')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->text('pickup_code')->nullable(); // chiffré
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 100);
            $table->unsignedSmallInteger('status_poll_count')->default(0);
            $table->timestampTz('next_status_poll_at')->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('send_currency')->references('code')->on('currencies');
            $table->foreign('receive_currency')->references('code')->on('currencies');
            $table->foreign('destination_country')->references('code')->on('countries');
            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['provider_id', 'provider_reference']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'next_status_poll_at']);
        });
        Check::enum('transfers', 'status', TransferStatus::class);
        Check::enum('transfers', 'payment_method', PaymentMethod::class);
        Check::enum('transfers', 'payout_method', PayoutMethod::class);
        Check::add('transfers', 'transfers_amounts_check',
            'send_amount > 0 AND receive_amount > 0 AND total_fees >= 0 AND total_debit = send_amount + total_fees AND fx_rate > 0');

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('transfer_id')->references('id')->on('transfers');
        });

        // Timeline du transfert (ajout seul).
        Schema::create('transfer_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->string('source', 10);
            $table->foreignUuid('actor_id')->nullable()->constrained('users');
            $table->jsonb('payload')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('created_at');

            $table->index(['transfer_id', 'created_at']);
        });
        Check::enum('transfer_events', 'from_status', TransferStatus::class);
        Check::enum('transfer_events', 'to_status', TransferStatus::class);
        Check::enum('transfer_events', 'source', TransferEventSource::class);
        Check::appendOnly('transfer_events');

        // Chaque appel sortant vers un provider (corps masqués : ni PAN, ni PIN, ni secret).
        Schema::create('provider_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained();
            $table->string('operation', 10);
            $table->string('request_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('is_success');
            $table->jsonb('request_body')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('created_at');

            $table->index(['provider_id', 'created_at']);
            $table->index('transfer_id');
        });
        Check::enum('provider_transactions', 'operation', ProviderOperation::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_transactions');
        Schema::dropIfExists('transfer_events');
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropForeign(['transfer_id']));
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('transfer_quotes');
        Schema::dropIfExists('beneficiaries');
    }
};
