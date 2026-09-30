<?php

use App\Domain\Wallet\Enums\WalletStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Enums\WalletType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 10)->default(WalletType::Personal->value);
            $table->string('status', 10)->default(WalletStatus::Active->value);
            $table->string('label')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
        });
        Check::enum('wallets', 'type', WalletType::class);
        Check::enum('wallets', 'status', WalletStatus::class);

        // Projection des soldes : recalculable depuis ledger_entries, jamais la source de vérité.
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->foreignUuid('ledger_account_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('available')->default(0);
            $table->bigInteger('reserved')->default(0);
            // Verrou optimiste.
            $table->unsignedBigInteger('version')->default(0);
            $table->timestampsTz();

            $table->unique(['wallet_id', 'currency_code']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });
        Check::add('wallet_balances', 'wallet_balances_non_negative', 'available >= 0 AND reserved >= 0');

        // Vue métier lisible par l'utilisateur, adossée à une transaction comptable.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('type', 15);
            $table->bigInteger('amount');
            $table->foreignUuid('ledger_transaction_id')->constrained()->restrictOnDelete();
            // FK vers transfers ajoutée dans la migration des transferts.
            $table->uuid('transfer_id')->nullable();
            $table->string('description');
            $table->timestampTz('created_at');

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['wallet_id', 'created_at']);
        });
        Check::enum('wallet_transactions', 'type', WalletTransactionType::class);
        Check::add('wallet_transactions', 'wallet_transactions_amount_non_zero', 'amount <> 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_balances');
        Schema::dropIfExists('wallets');
    }
};
