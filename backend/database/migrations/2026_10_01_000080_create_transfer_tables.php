<?php

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\QuoteStatus;
use App\Domain\Payments\Enums\TransferEventSource;
use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Enums\ProviderOperation;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transferts (ADR 0004 : Cashop ne détient pas de fonds clients).
 * Chaque transfert est financé par un paiement Mobile Money dédié, puis créé auprès d'un réseau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // Données nominatives chiffrées au niveau applicatif.
            $table->text('first_name');
            $table->text('last_name');
            $table->text('phone_e164')->nullable();
            $table->char('country_code', 2);
            $table->text('city')->nullable();
            $table->string('relationship', 40)->nullable();
            // Champs complémentaires exigés par un réseau (chiffrés, structure configurable).
            $table->text('details')->nullable();
            // Empreinte (HMAC) pour détecter les doublons sans déchiffrer.
            $table->string('identity_hash', 64);
            $table->string('display_name', 120); // ex. « Paul M. », sans donnée complète
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('country_code')->references('code')->on('countries');
            $table->unique(['user_id', 'identity_hash']);
        });

        Schema::create('transfer_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained(); // réseau de transfert
            $table->foreignUuid('funding_provider_id')->constrained('providers'); // portefeuille Mobile Money
            $table->string('environment', 12);
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
            $table->boolean('is_simulated');
            $table->string('status', 10)->default(QuoteStatus::Active->value);
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->foreign('send_currency')->references('code')->on('currencies');
            $table->foreign('receive_currency')->references('code')->on('currencies');
            $table->foreign('destination_country')->references('code')->on('countries');
            $table->index(['user_id', 'created_at']);
        });
        Check::enum('transfer_quotes', 'status', QuoteStatus::class);
        Check::enum('transfer_quotes', 'environment', ProviderMode::class);
        Check::enum('transfer_quotes', 'payment_method', PaymentMethod::class);
        Check::enum('transfer_quotes', 'payout_method', PayoutMethod::class);
        Check::add('transfer_quotes', 'transfer_quotes_amounts_check',
            'send_amount > 0 AND receive_amount > 0 AND total_fees >= 0 AND total_debit = send_amount + total_fees AND fx_rate > 0');

        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary(); // = transaction_id exposé
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('quote_id')->unique()->constrained('transfer_quotes')->restrictOnDelete();
            $table->foreignUuid('beneficiary_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('funding_provider_id')->constrained('providers')->restrictOnDelete();
            $table->text('funding_account'); // MSISDN chiffré
            $table->string('funding_account_hash', 64);
            // Environnement figé à la création : une opération simulée ne passe jamais en production.
            $table->string('environment', 12);
            $table->string('status', 30);
            // Montants et taux figés à la confirmation du devis.
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
            // Scénario de démonstration (environnement mock uniquement).
            $table->string('simulation_scenario', 30)->nullable();
            // Traçabilité
            $table->uuid('correlation_id')->index();
            $table->string('external_reference', 64)->unique(); // référence Cashop transmise aux prestataires
            $table->string('provider_reference')->nullable();   // référence du réseau (MTCN…)
            $table->string('provider_request_id')->nullable();
            $table->text('pickup_code')->nullable();             // chiffré
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 100);
            $table->unsignedSmallInteger('status_poll_count')->default(0);
            $table->timestampTz('next_status_poll_at')->nullable();
            $table->timestampTz('payment_confirmed_at')->nullable();
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
        Check::enum('transfers', 'environment', ProviderMode::class);
        Check::enum('transfers', 'payment_method', PaymentMethod::class);
        Check::enum('transfers', 'payout_method', PayoutMethod::class);
        Check::add('transfers', 'transfers_amounts_check',
            'send_amount > 0 AND receive_amount > 0 AND total_fees >= 0 AND total_debit = send_amount + total_fees AND fx_rate > 0');
        Check::add('transfers', 'transfers_simulation_only_in_mock',
            "simulation_scenario IS NULL OR environment = 'mock'");

        // Timeline du transfert (ajout seul).
        Schema::create('transfer_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
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

        // Mouvements Mobile Money (collecte du financement, remboursement, versement).
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary(); // = référence externe transmise à l'opérateur
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained();
            $table->string('direction', 12);
            $table->string('environment', 12);
            $table->text('account'); // MSISDN chiffré
            $table->string('account_hash', 64);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 10);
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies');
            $table->index(['status', 'updated_at']);
        });
        Check::enum('payment_attempts', 'direction', PaymentDirection::class);
        Check::enum('payment_attempts', 'environment', ProviderMode::class);
        Check::enum('payment_attempts', 'status', PaymentAttemptStatus::class);
        Check::add('payment_attempts', 'payment_attempts_amount_positive', 'amount > 0');
        // Anti double débit garanti par la base : une seule collecte par transfert.
        DB::statement("CREATE UNIQUE INDEX payment_attempts_one_collection ON payment_attempts (transfer_id) WHERE direction = 'COLLECTION'");

        // Chaque appel sortant vers un prestataire (corps masqués : ni PIN, ni secret).
        Schema::create('provider_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained();
            $table->string('operation', 20);
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

        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->string('number', 30)->unique();
            $table->string('kind', 12); // TRANSFER | REFUND
            $table->boolean('is_simulation');
            $table->jsonb('payload'); // instantané figé au moment de l'émission
            $table->timestampTz('issued_at');

            $table->unique(['transfer_id', 'kind']);
        });
        Check::add('receipts', 'receipts_kind_check', "kind IN ('TRANSFER', 'REFUND')");
        Check::appendOnly('receipts');

        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->string('status_before', 30);
            $table->string('status_after', 30)->nullable();
            $table->string('payment_status_seen', 20)->nullable();
            $table->string('transfer_status_seen', 40)->nullable();
            $table->string('outcome', 30); // RESOLVED | STILL_UNKNOWN | ESCALATED
            $table->jsonb('evidence')->nullable();
            $table->foreignUuid('actor_id')->nullable()->constrained('users');
            $table->timestampTz('created_at');

            $table->index(['transfer_id', 'created_at']);
        });
        Check::add('reconciliation_records', 'reconciliation_outcome_check', "outcome IN ('RESOLVED', 'STILL_UNKNOWN', 'ESCALATED')");
        Check::appendOnly('reconciliation_records');
    }

    public function down(): void
    {
        foreach (['reconciliation_records', 'receipts', 'provider_transactions', 'payment_attempts', 'transfer_events', 'transfers', 'transfer_quotes', 'beneficiaries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
