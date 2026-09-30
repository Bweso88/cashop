<?php

use App\Domain\Fees\Enums\FeeComponent;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Risk\Enums\LimitScope;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Règles évaluées par le FeeEngine. Critères NULL = "toutes valeurs".
        Schema::create('fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('component', 15);
            $table->foreignUuid('provider_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('source_country', 2)->nullable();
            $table->char('destination_country', 2)->nullable();
            $table->char('currency_code', 3);
            $table->string('payment_method', 20)->nullable();
            $table->string('payout_method', 20)->nullable();
            $table->bigInteger('min_amount')->nullable();
            $table->bigInteger('max_amount')->nullable();
            $table->bigInteger('fixed_amount')->default(0);
            $table->unsignedInteger('percentage_bps')->default(0);
            $table->integer('priority')->default(0);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('source_country')->references('code')->on('countries');
            $table->foreign('destination_country')->references('code')->on('countries');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });
        Check::enum('fees', 'component', FeeComponent::class);
        Check::enum('fees', 'payment_method', PaymentMethod::class);
        Check::enum('fees', 'payout_method', PayoutMethod::class);
        Check::add('fees', 'fees_amounts_check', 'fixed_amount >= 0 AND (valid_to IS NULL OR valid_to > valid_from)');

        Schema::create('fx_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 24, 12);
            $table->unsignedInteger('spread_bps')->default(0);
            $table->string('source', 40);
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestampsTz();

            $table->foreign('base_currency')->references('code')->on('currencies');
            $table->foreign('quote_currency')->references('code')->on('currencies');
            $table->index(['base_currency', 'quote_currency', 'valid_from']);
        });
        Check::add('fx_rates', 'fx_rates_rate_positive', 'rate > 0 AND base_currency <> quote_currency');

        // Plafonds par niveau KYC : valeurs fournies par la conformité, aucune valeur réglementaire par défaut.
        Schema::create('limit_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kyc_level_code', 20);
            $table->string('scope', 20);
            $table->char('currency_code', 3);
            $table->bigInteger('max_amount')->nullable();
            $table->unsignedInteger('max_count')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign('kyc_level_code')->references('code')->on('kyc_levels');
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreign('country_code')->references('code')->on('countries');
        });
        Check::enum('limit_rules', 'scope', LimitScope::class);
        Check::add('limit_rules', 'limit_rules_has_limit', 'max_amount IS NOT NULL OR max_count IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('limit_rules');
        Schema::dropIfExists('fx_rates');
        Schema::dropIfExists('fees');
    }
};
