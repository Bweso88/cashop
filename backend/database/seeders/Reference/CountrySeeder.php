<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Pays référencés (codes ISO 3166-1, indicatifs UIT). Tous désactivés par défaut :
     * l'ouverture d'un pays est une décision d'exploitation (agrément, partenaires).
     */
    public const COUNTRIES = [
        // Zone CEMAC (XAF)
        ['CM', 'Cameroun', 'XAF', '237'],
        ['CG', 'Congo', 'XAF', '242'],
        ['GA', 'Gabon', 'XAF', '241'],
        ['TD', 'Tchad', 'XAF', '235'],
        ['CF', 'République centrafricaine', 'XAF', '236'],
        ['GQ', 'Guinée équatoriale', 'XAF', '240'],
        // Zone UEMOA (XOF)
        ['CI', "Côte d'Ivoire", 'XOF', '225'],
        ['SN', 'Sénégal', 'XOF', '221'],
        // Autres
        ['CD', 'République démocratique du Congo', 'CDF', '243'],
        ['FR', 'France', 'EUR', '33'],
        ['BE', 'Belgique', 'EUR', '32'],
        ['DE', 'Allemagne', 'EUR', '49'],
        ['GB', 'Royaume-Uni', 'GBP', '44'],
        ['US', 'États-Unis', 'USD', '1'],
    ];

    public function run(): void
    {
        $now = now();
        DB::table('countries')->upsert(
            array_map(fn ($c) => [
                'code' => $c[0], 'name' => $c[1], 'default_currency' => $c[2], 'dial_code' => $c[3],
                'created_at' => $now, 'updated_at' => $now,
            ], self::COUNTRIES),
            ['code'],
            ['name', 'default_currency', 'dial_code', 'updated_at'],
        );
    }
}
