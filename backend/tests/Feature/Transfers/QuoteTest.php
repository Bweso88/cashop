<?php

namespace Tests\Feature\Transfers;

use App\Domain\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class QuoteTest extends TransferTestCase
{
    #[Test]
    public function devis_detaille_frais_taux_et_montant_recu(): void
    {
        $quote = $this->quote()->assertCreated()->json();

        // Frais réseau fictifs WU : max(1 500, 2 % de 100 000) = 2 000 XAF.
        // Frais de service Cashop (DEV_ONLY) : 500 + 0,5 % = 1 000 XAF.
        $this->assertSame([
            ['component' => 'PROVIDER', 'label' => 'Frais Western Union (fictifs — simulation)', 'amount' => 2000],
            ['component' => 'FIXED', 'label' => 'DEV_ONLY frais de service fixes', 'amount' => 500],
            ['component' => 'PERCENTAGE', 'label' => 'DEV_ONLY frais de service proportionnels', 'amount' => 500],
        ], $quote['fees']);
        $this->assertSame(['amount' => 3000, 'currency' => 'XAF'], $quote['total_fees']);
        $this->assertSame(['amount' => 103000, 'currency' => 'XAF'], $quote['total_debit']);
        // 100 000 XAF au taux de parité 1/655,957 = 152,449… € → 152,44 € (arrondi inférieur).
        $this->assertSame(['amount' => 15244, 'currency' => 'EUR'], $quote['receive']);
        $this->assertStringStartsWith('0.00152449', $quote['fx_rate']);
        $this->assertTrue($quote['is_simulated']);
        $this->assertSame('Devis simulé : frais et taux fictifs.', $quote['simulation_notice']);
    }

    #[Test]
    public function chaque_reseau_a_ses_frais(): void
    {
        $fee = fn (string $network, string $country) => $this->quote(['network' => $network, 'destination_country' => $country])->assertCreated()->json('fees.0.amount');

        $this->assertSame(2000, $fee('western_union', 'FR'));
        $this->assertSame(1800, $fee('moneygram', 'FR'));
        $this->assertSame(1500, $fee('ria', 'FR'));
    }

    #[Test]
    public function corridor_non_propose(): void
    {
        $this->quote(['network' => 'moneygram', 'destination_country' => 'CI'])->assertStatus(422)->assertJsonPath('code', 'CORRIDOR_UNAVAILABLE');
        $this->quote(['payout_method' => 'mobile_money'])->assertStatus(422)->assertJsonPath('code', 'CORRIDOR_UNAVAILABLE');
    }

    #[Test]
    public function plafond_du_niveau_kyc(): void
    {
        $this->quote(['send_amount' => 1_000_001])->assertStatus(422)->assertJsonPath('code', 'LIMIT_EXCEEDED');
    }

    #[Test]
    public function sans_plafond_configure_l_envoi_est_refuse(): void
    {
        DB::table('limit_rules')->delete();

        $this->quote()->assertStatus(403)->assertJsonPath('code', 'LIMITS_NOT_CONFIGURED');
    }

    #[Test]
    public function la_production_est_desactivee_par_defaut(): void
    {
        Provider::query()->whereIn('code', ['western_union', 'mtn_momo'])->update(['mode' => 'production']);

        $this->quote()->assertStatus(503)->assertJsonPath('code', 'PROVIDER_UNAVAILABLE')
            ->assertJsonPath('message', 'Environnement de production désactivé (CASHOP_PRODUCTION_ENABLED=false).');
    }

    #[Test]
    public function aucun_connecteur_officiel_n_est_invente(): void
    {
        Provider::query()->whereIn('code', ['western_union', 'mtn_momo'])->update(['mode' => 'sandbox']);

        $response = $this->quote()->assertStatus(503)->assertJsonPath('code', 'PROVIDER_UNAVAILABLE');
        $this->assertStringContainsString('Connecteur officiel Western Union non disponible', $response->json('message'));
    }

    #[Test]
    public function portefeuille_et_reseau_doivent_partager_l_environnement(): void
    {
        Provider::query()->where('code', 'western_union')->update(['mode' => 'sandbox']);

        $this->quote()->assertStatus(422)->assertJsonPath('code', 'ENVIRONMENT_MISMATCH');
    }

    #[Test]
    public function catalogue_reseaux_et_financement(): void
    {
        $this->getJson('/api/v1/catalog/funding-providers', $this->headers)->assertOk()
            ->assertJsonPath('data.0.name', 'Airtel Money')->assertJsonPath('data.1.name', 'MTN Mobile Money')
            ->assertJsonPath('data.0.is_simulation', true);

        $networks = $this->getJson('/api/v1/catalog/networks', $this->headers)->assertOk()->json('data');
        $this->assertSame(['MoneyGram', 'Ria Money Transfer', 'Western Union'], array_column($networks, 'name'));

        $this->getJson('/api/v1/catalog/networks/western_union/fields?country=CM&payout_method=mobile_money', $this->headers)->assertOk()
            ->assertJsonPath('data.2.name', 'phone_e164')->assertJsonPath('data.2.required', true)->assertJsonPath('data.2.source', 'SIMULATION');

        $this->getJson('/api/v1/catalog/simulation-scenarios', $this->headers)->assertOk()->assertJsonPath('enabled', true)->assertJsonCount(7, 'data');
    }
}
