<?php

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Providers\Enums\ConfirmationStatus;
use App\Domain\Providers\Enums\ProviderCapability;
use App\Domain\Providers\Enums\ProviderDirection;
use App\Domain\Providers\Enums\ProviderHealthStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('adapter_class')->nullable();
            $table->string('mock_adapter_class')->nullable();
            $table->string('mode', 12)->default(ProviderMode::Mock->value);
            $table->boolean('is_enabled')->default(false);
            $table->string('health_status', 12)->default(ProviderHealthStatus::Unknown->value);
            $table->timestampTz('health_checked_at')->nullable();
            $table->timestampsTz();
        });
        Check::enum('providers', 'mode', ProviderMode::class);
        Check::enum('providers', 'health_status', ProviderHealthStatus::class);

        Schema::create('provider_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 20);
            $table->boolean('is_supported')->default(false);
            $table->string('confirmation_status', 15)->default(ConfirmationStatus::NotConfirmed->value);
            // Lien vers la page de documentation officielle qui confirme la capacité.
            $table->text('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['provider_id', 'capability']);
        });
        Check::enum('provider_capabilities', 'capability', ProviderCapability::class);
        Check::enum('provider_capabilities', 'confirmation_status', ConfirmationStatus::class);
        // Une capacité ne peut être déclarée supportée que si elle est confirmée par la documentation.
        Check::add('provider_capabilities', 'provider_capabilities_supported_requires_confirmation',
            "NOT is_supported OR confirmation_status = 'CONFIRMED'");

        Schema::create('provider_country_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->string('direction', 10)->default(ProviderDirection::Both->value);
            $table->string('mode', 12)->default(ProviderMode::Mock->value);
            $table->boolean('is_enabled')->default(false);
            // Paramètres non sensibles uniquement (ex. environnement cible).
            $table->jsonb('settings')->default('{}');
            // Référence vers le gestionnaire de secrets — jamais la valeur du secret.
            $table->string('credentials_ref')->nullable();
            $table->bigInteger('min_amount')->nullable();
            $table->bigInteger('max_amount')->nullable();
            $table->string('confirmation_status', 15)->default(ConfirmationStatus::NotConfirmed->value);
            $table->timestampsTz();

            $table->unique(['provider_id', 'country_code', 'currency_code']);
            $table->foreign('country_code')->references('code')->on('countries');
            $table->foreign('currency_code')->references('code')->on('currencies');
        });
        Check::enum('provider_country_configs', 'direction', ProviderDirection::class);
        Check::enum('provider_country_configs', 'mode', ProviderMode::class);
        Check::enum('provider_country_configs', 'confirmation_status', ConfirmationStatus::class);
        Check::add('provider_country_configs', 'provider_country_configs_amounts_check',
            'min_amount IS NULL OR max_amount IS NULL OR min_amount <= max_amount');

        foreach (['country_payment_methods' => PaymentMethod::class, 'country_payout_methods' => PayoutMethod::class] as $name => $enum) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->char('country_code', 2);
                $table->string('method', 20);
                $table->foreignUuid('provider_id')->nullable()->constrained()->cascadeOnDelete();
                $table->char('currency_code', 3);
                $table->boolean('is_enabled')->default(false);
                $table->timestampsTz();

                $table->foreign('country_code')->references('code')->on('countries');
                $table->foreign('currency_code')->references('code')->on('currencies');
            });
            Check::enum($name, 'method', $enum);
            // NULLS NOT DISTINCT : une seule ligne "tous providers" par combinaison.
            DB::statement(
                "CREATE UNIQUE INDEX {$name}_unique ON {$name} (country_code, method, currency_code, provider_id) NULLS NOT DISTINCT"
            );
        }

        Schema::create('provider_health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2)->nullable();
            $table->timestampTz('checked_at');
            $table->string('status', 12);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();

            $table->index(['provider_id', 'checked_at']);
        });
        Check::enum('provider_health_checks', 'status', ProviderHealthStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_health_checks');
        Schema::dropIfExists('country_payout_methods');
        Schema::dropIfExists('country_payment_methods');
        Schema::dropIfExists('provider_country_configs');
        Schema::dropIfExists('provider_capabilities');
        Schema::dropIfExists('providers');
    }
};
