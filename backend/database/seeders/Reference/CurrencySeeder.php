<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Décimales selon ISO 4217. Devises des wallets (actives) : XAF, EUR, USD, GBP.
     * Les autres servent de devise par défaut à des pays référencés et restent inactives.
     */
    public const CURRENCIES = [
        ['code' => 'XAF', 'name' => 'Franc CFA (BEAC)', 'minor_units' => 0, 'is_active' => true],
        ['code' => 'EUR', 'name' => 'Euro', 'minor_units' => 2, 'is_active' => true],
        ['code' => 'USD', 'name' => 'Dollar américain', 'minor_units' => 2, 'is_active' => true],
        ['code' => 'GBP', 'name' => 'Livre sterling', 'minor_units' => 2, 'is_active' => true],
        ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO)', 'minor_units' => 0, 'is_active' => false],
        ['code' => 'CDF', 'name' => 'Franc congolais', 'minor_units' => 2, 'is_active' => false],
    ];

    public function run(): void
    {
        $now = now();
        DB::table('currencies')->upsert(
            array_map(fn ($c) => $c + ['created_at' => $now, 'updated_at' => $now], self::CURRENCIES),
            ['code'],
            ['name', 'minor_units', 'updated_at'], // is_active n'est pas écrasé : réglage d'exploitation
        );
    }
}
