<?php

namespace Database\Seeders;

use Database\Seeders\Development\DevelopmentSeeder;
use Database\Seeders\Reference\ReferenceDataSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Les données de référence sont chargées partout ; les données de démonstration
     * (utilisateurs, corridors Mock, grilles DEV_ONLY) uniquement en local et en test.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
