<?php

namespace Database\Seeders\Development;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Données de démonstration — local et testing uniquement.
 * Toutes les valeurs chiffrées sont fictives et marquées DEV_ONLY.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder ne doit jamais être exécuté hors local/testing.');
        }

        $this->call([
            MockCorridorSeeder::class,
            DevPricingSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
