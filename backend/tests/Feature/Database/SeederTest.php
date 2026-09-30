<?php

namespace Tests\Feature\Database;

use Database\Seeders\Development\DevelopmentSeeder;
use Database\Seeders\Reference\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'currencies', 'countries', 'providers', 'provider_capabilities', 'kyc_levels',
        'roles', 'permissions', 'ledger_accounts', 'risk_rules', 'fees', 'fx_rates', 'limit_rules', 'users',
    ];

    private function counts(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    #[Test]
    public function les_seeders_sont_idempotents(): void
    {
        $this->seed();
        $first = $this->counts();
        $this->seed();

        $this->assertSame($first, $this->counts());
    }

    #[Test]
    public function seules_les_capacites_documentees_officiellement_sont_confirmees(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $confirmed = DB::table('provider_capabilities as c')
            ->join('providers as p', 'p.id', '=', 'c.provider_id')
            ->where('c.confirmation_status', 'CONFIRMED')
            ->orderBy('c.capability')
            ->get(['p.code', 'c.capability', 'c.source_reference']);

        $this->assertSame(['moneygram'], $confirmed->pluck('code')->unique()->values()->all());
        $this->assertSame(['commit', 'create', 'quote'], $confirmed->pluck('capability')->all());
        $this->assertTrue($confirmed->every(fn ($c) => str_starts_with($c->source_reference, 'https://developer.moneygram.com/')));
    }

    #[Test]
    public function les_donnees_de_reference_n_activent_aucun_provider(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame(0, DB::table('providers')->where('is_enabled', true)->count());
        $this->assertSame(0, DB::table('countries')->where('is_send_enabled', true)->count());
        $this->assertSame(0, DB::table('risk_rules')->where('is_active', true)->count());
    }

    #[Test]
    public function le_seeder_de_developpement_refuse_la_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        // Appel direct : la commande db:seed demanderait une confirmation interactive en production.
        $this->app->make(DevelopmentSeeder::class)->run();
    }
}
