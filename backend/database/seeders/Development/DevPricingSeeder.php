<?php

namespace Database\Seeders\Development;

use App\Domain\Fees\Enums\FeeComponent;
use App\Domain\Risk\Enums\LimitScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Frais, taux et plafonds FICTIFS pour le développement. Aucune de ces valeurs n'est un tarif
 * provider ni une règle réglementaire : en production, la finance et la conformité les saisissent.
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

        // Parité fixe EUR/XAF (1 EUR = 655,957 XAF) ; les autres taux sont illustratifs.
        $rates = [
            ['EUR', 'XAF', '655.957000000000', 'FIXED_PARITY'],
            ['EUR', 'USD', '1.080000000000', 'DEV_ONLY_ILLUSTRATIVE'],
            ['EUR', 'GBP', '0.850000000000', 'DEV_ONLY_ILLUSTRATIVE'],
        ];
        foreach ($rates as [$base, $quote, $rate, $source]) {
            DB::table('fx_rates')->insert([
                'id' => (string) Str::uuid7(),
                'base_currency' => $base,
                'quote_currency' => $quote,
                'rate' => $rate,
                'spread_bps' => $source === 'FIXED_PARITY' ? 0 : 100,
                'source' => $source,
                'valid_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $fees = [
            ['DEV_ONLY frais fixes EUR', FeeComponent::Fixed, 'EUR', 99, 0],
            ['DEV_ONLY commission EUR', FeeComponent::Percentage, 'EUR', 0, 150],
            ['DEV_ONLY frais fixes XAF', FeeComponent::Fixed, 'XAF', 500, 0],
            ['DEV_ONLY commission XAF', FeeComponent::Percentage, 'XAF', 0, 150],
        ];
        foreach ($fees as [$name, $component, $currency, $fixed, $bps]) {
            DB::table('fees')->insert([
                'id' => (string) Str::uuid7(),
                'name' => $name,
                'component' => $component->value,
                'currency_code' => $currency,
                'fixed_amount' => $fixed,
                'percentage_bps' => $bps,
                'valid_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Plafonds DEV_ONLY (unités mineures) — LEVEL_0 ne peut pas envoyer.
        $limits = [
            ['LEVEL_1', LimitScope::PerTransaction, 'EUR', 100_000], ['LEVEL_1', LimitScope::Monthly, 'EUR', 500_000],
            ['LEVEL_1', LimitScope::PerTransaction, 'XAF', 650_000], ['LEVEL_1', LimitScope::Monthly, 'XAF', 3_250_000],
            ['LEVEL_2', LimitScope::PerTransaction, 'EUR', 1_000_000], ['LEVEL_2', LimitScope::Monthly, 'EUR', 5_000_000],
        ];
        foreach ($limits as [$level, $scope, $currency, $max]) {
            DB::table('limit_rules')->insert([
                'id' => (string) Str::uuid7(),
                'kyc_level_code' => $level,
                'scope' => $scope->value,
                'currency_code' => $currency,
                'max_amount' => $max,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
