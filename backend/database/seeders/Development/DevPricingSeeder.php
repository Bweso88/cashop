<?php

namespace Database\Seeders\Development;

use App\Domain\Fees\Enums\FeeComponent;
use App\Domain\Risk\Enums\LimitScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frais Cashop, taux et plafonds FICTIFS pour la démonstration. Aucune de ces valeurs n'est un
 * tarif de prestataire ni une règle réglementaire : en production, la finance et la conformité
 * de l'établissement exploitant les saisissent.
 */
class DevPricingSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent : ces grilles ne sont chargées qu'une fois.
        if (DB::table('fees')->where('name', 'like', 'DEV_ONLY%')->exists()) {
            return;
        }

        $now = now();

        // Parités fixes : 1 EUR = 655,957 XAF = 655,957 XOF. Les autres taux sont illustratifs.
        $rates = [
            ['EUR', 'XAF', '655.957000000000', 0, 'FIXED_PARITY'],
            ['EUR', 'XOF', '655.957000000000', 0, 'FIXED_PARITY'],
            ['EUR', 'USD', '1.080000000000', 150, 'DEV_ONLY_ILLUSTRATIVE'],
            ['EUR', 'GBP', '0.850000000000', 150, 'DEV_ONLY_ILLUSTRATIVE'],
        ];
        foreach ($rates as [$base, $quote, $rate, $spread, $source]) {
            DB::table('fx_rates')->insert([
                'id' => (string) Str::uuid7(), 'base_currency' => $base, 'quote_currency' => $quote, 'rate' => $rate,
                'spread_bps' => $spread, 'source' => $source, 'valid_from' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Frais de service Cashop (en plus des frais du réseau), en XAF.
        $fees = [
            ['DEV_ONLY frais de service fixes', FeeComponent::Fixed, 500, 0],
            ['DEV_ONLY frais de service proportionnels', FeeComponent::Percentage, 0, 50],
        ];
        foreach ($fees as [$name, $component, $fixed, $bps]) {
            DB::table('fees')->insert([
                'id' => (string) Str::uuid7(), 'name' => $name, 'component' => $component->value, 'currency_code' => 'XAF',
                'source_country' => 'CG', 'fixed_amount' => $fixed, 'percentage_bps' => $bps, 'valid_from' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Plafonds DEV_ONLY en XAF (montant envoyé).
        $limits = [
            ['LEVEL_0', LimitScope::PerTransaction, 200_000], ['LEVEL_0', LimitScope::Monthly, 500_000],
            ['LEVEL_1', LimitScope::PerTransaction, 1_000_000], ['LEVEL_1', LimitScope::Monthly, 5_000_000],
            ['LEVEL_2', LimitScope::PerTransaction, 5_000_000], ['LEVEL_2', LimitScope::Monthly, 20_000_000],
        ];
        foreach ($limits as [$level, $scope, $max]) {
            DB::table('limit_rules')->insert([
                'id' => (string) Str::uuid7(), 'kyc_level_code' => $level, 'scope' => $scope->value, 'currency_code' => 'XAF',
                'max_amount' => $max, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
