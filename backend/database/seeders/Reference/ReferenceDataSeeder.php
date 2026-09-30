<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;

/**
 * Données de référence, idempotentes (upsert) : exécutables à chaque déploiement.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            ProviderSeeder::class,
            KycLevelSeeder::class,
            RolePermissionSeeder::class,
            SystemLedgerAccountSeeder::class,
            RiskRuleCatalogSeeder::class,
        ]);
    }
}
