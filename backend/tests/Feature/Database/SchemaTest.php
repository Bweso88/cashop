<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** Tables exigées par le cahier des charges (docs/DATABASE.md). */
    private const REQUIRED_TABLES = [
        'users', 'user_profiles', 'kyc_profiles', 'kyc_documents', 'kyc_verifications',
        'wallets', 'wallet_balances', 'wallet_transactions',
        'ledger_accounts', 'ledger_transactions', 'ledger_entries',
        'beneficiaries', 'transfers', 'transfer_quotes', 'transfer_events', 'provider_transactions',
        'providers', 'provider_capabilities', 'provider_country_configs',
        'countries', 'currencies', 'country_payment_methods', 'country_payout_methods',
        'fees', 'fx_rates', 'refunds', 'disputes', 'webhooks',
        'risk_events', 'risk_scores', 'compliance_cases',
        'notifications', 'audit_logs', 'idempotency_keys',
    ];

    #[Test]
    public function toutes_les_tables_requises_existent(): void
    {
        foreach (self::REQUIRED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
    }

    #[Test]
    public function aucun_montant_n_est_stocke_en_flottant(): void
    {
        $floatColumns = collect(Schema::getTables())
            ->flatMap(fn (array $t) => collect(Schema::getColumns($t['name']))
                ->filter(fn (array $c) => in_array($c['type_name'], ['float4', 'float8', 'real', 'double precision'], true))
                ->map(fn (array $c) => "{$t['name']}.{$c['name']}"))
            ->all();

        $this->assertSame([], $floatColumns);
    }
}
