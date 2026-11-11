<?php

use App\Domain\Payments\Enums\IncomingTransferStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réceptions (parcours B) : un transfert envoyé depuis l'étranger est versé PAR LE RÉSEAU sur le
 * portefeuille Mobile Money du bénéficiaire. Cashop ne reçoit ni ne détient les fonds : aucune
 * écriture de ledger, seulement la trace de la demande et de son résultat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('provider_id')->constrained(); // réseau émetteur
            $table->foreignUuid('payout_provider_id')->constrained('providers'); // portefeuille crédité
            $table->string('environment', 12);
            $table->string('network_reference', 64);
            $table->string('sender_name', 120);
            $table->char('sender_country', 2);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->text('payout_msisdn'); // chiffré
            $table->string('payout_msisdn_hash', 64);
            $table->string('status', 30);
            $table->string('payout_reference', 64)->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->string('idempotency_key', 100);
            $table->string('correlation_id', 64)->nullable();
            $table->timestampTz('paid_out_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            // Une référence réseau ne peut être réclamée qu'une fois, par une seule personne.
            $table->unique(['provider_id', 'network_reference']);
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });
        Check::enum('incoming_transfers', 'status', IncomingTransferStatus::class);
        Check::enum('incoming_transfers', 'environment', ProviderMode::class);
        Check::add('incoming_transfers', 'incoming_transfers_amount_positive', 'amount > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_transfers');
    }
};
