<?php

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\LedgerAccountType;
use App\Domain\Ledger\Enums\LedgerTransactionType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger en partie double (docs/DATABASE.md §Ledger, ADR 0003).
 *
 * Invariants garantis par la base, indépendamment du code PHP :
 *  1. Chaque ledger_transaction est équilibrée par devise (Σ débits = Σ crédits),
 *     vérifié au COMMIT par un trigger différé.
 *  2. Chaque ledger_transaction comporte au moins deux écritures.
 *  3. Une écriture est dans la devise de son compte.
 *  4. ledger_transactions et ledger_entries sont immuables (ni UPDATE ni DELETE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Ex. wallet:{wallet_id}:EUR, in_flight:EUR, provider_clearing:mtn_momo:XAF, fee_revenue:EUR
            $table->string('code')->unique();
            $table->string('type', 10);
            $table->char('currency_code', 3);
            $table->string('normal_balance', 6);
            $table->string('owner_type', 40)->nullable();
            $table->uuid('owner_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['owner_type', 'owner_id']);
        });
        Check::enum('ledger_accounts', 'type', LedgerAccountType::class);
        Check::enum('ledger_accounts', 'normal_balance', EntryDirection::class);

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->string('reference_type', 40)->nullable();
            $table->uuid('reference_id')->nullable();
            // Empêche de comptabiliser deux fois le même événement métier.
            $table->string('idempotency_key')->unique();
            $table->text('description');
            $table->timestampTz('posted_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestampTz('created_at');

            $table->index(['reference_type', 'reference_id']);
        });
        Check::enum('ledger_transactions', 'type', LedgerTransactionType::class);

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ledger_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 6);
            $table->bigInteger('amount');
            $table->char('currency_code', 3);
            $table->timestampTz('created_at');

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['ledger_account_id', 'created_at']);
        });
        Check::enum('ledger_entries', 'direction', EntryDirection::class);
        Check::add('ledger_entries', 'ledger_entries_amount_positive', 'amount > 0');

        DB::unprepared(<<<'SQL'
            -- (3) Devise de l'écriture = devise du compte
            CREATE OR REPLACE FUNCTION cashop_ledger_entry_currency() RETURNS trigger AS $$
            BEGIN
                IF NEW.currency_code <> (SELECT currency_code FROM ledger_accounts WHERE id = NEW.ledger_account_id) THEN
                    RAISE EXCEPTION 'Écriture en % sur un compte d''une autre devise', NEW.currency_code
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER ledger_entries_currency
            BEFORE INSERT ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION cashop_ledger_entry_currency();

            -- (1) Équilibre par devise, vérifié au COMMIT
            CREATE OR REPLACE FUNCTION cashop_ledger_balanced() RETURNS trigger AS $$
            DECLARE
                bad_currency char(3);
            BEGIN
                SELECT currency_code INTO bad_currency
                FROM ledger_entries
                WHERE ledger_transaction_id = NEW.ledger_transaction_id
                GROUP BY currency_code
                HAVING SUM(CASE WHEN direction = 'DEBIT' THEN amount ELSE -amount END) <> 0
                LIMIT 1;

                IF FOUND THEN
                    RAISE EXCEPTION 'Transaction comptable % déséquilibrée en %', NEW.ledger_transaction_id, bad_currency
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER ledger_entries_balanced
            AFTER INSERT ON ledger_entries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION cashop_ledger_balanced();

            -- (2) Au moins deux écritures par transaction, vérifié au COMMIT
            CREATE OR REPLACE FUNCTION cashop_ledger_min_entries() RETURNS trigger AS $$
            BEGIN
                IF (SELECT COUNT(*) FROM ledger_entries WHERE ledger_transaction_id = NEW.id) < 2 THEN
                    RAISE EXCEPTION 'Transaction comptable % : au moins deux écritures requises', NEW.id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER ledger_transactions_min_entries
            AFTER INSERT ON ledger_transactions
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION cashop_ledger_min_entries();
        SQL);

        // (4) Immuabilité : toute correction passe par une contre-écriture.
        Check::appendOnly('ledger_transactions');
        Check::appendOnly('ledger_entries');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS cashop_ledger_entry_currency();
            DROP FUNCTION IF EXISTS cashop_ledger_balanced();
            DROP FUNCTION IF EXISTS cashop_ledger_min_entries();
        SQL);
    }
};
