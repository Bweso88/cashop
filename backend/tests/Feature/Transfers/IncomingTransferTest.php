<?php

namespace Tests\Feature\Transfers;

use App\Domain\Payments\Models\IncomingTransfer;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class IncomingTransferTest extends TransferTestCase
{
    private function simulate(string $scenario = 'SUCCESS', string $network = 'moneygram', ?array $headers = null): string
    {
        return $this->postJson('/api/v1/incoming/simulate', ['network' => $network, 'amount' => 75000, 'scenario' => $scenario], $headers ?? $this->headers)
            ->assertCreated()->json('reference');
    }

    private function claim(string $reference, array $overrides = [], ?string $key = null): TestResponse
    {
        return $this->postJson('/api/v1/incoming', array_merge([
            'network' => 'moneygram', 'reference' => $reference, 'payout_provider' => 'airtel_money',
            'payout_msisdn' => '+242050000009', 'pin' => '482915',
        ], $overrides), $this->headers + ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    #[Test]
    public function reception_complete_versee_sur_mobile_money(): void
    {
        $reference = $this->simulate();
        $this->assertStringStartsWith('SIM-IN-MG-', $reference);

        $this->postJson('/api/v1/incoming/lookup', ['network' => 'moneygram', 'reference' => strtolower($reference)], $this->headers)->assertOk()
            ->assertJsonPath('status', 'AVAILABLE')->assertJsonPath('amount.amount', 75000)->assertJsonPath('amount.currency', 'XAF')
            ->assertJsonPath('is_simulation', true);

        config(['cashop.simulation.delay_seconds' => 30]); // le réseau met un peu de temps à verser
        $id = $this->claim($reference)->assertCreated()->assertJsonPath('status', 'PAYOUT_PENDING')
            ->assertJsonPath('payout.provider.name', 'Airtel Money')->assertJsonPath('payout.msisdn_masked', '+242 ••• 009')->json('id');

        $this->travel(31)->seconds();
        $this->postJson("/api/v1/incoming/{$id}/refresh", [], $this->headers)->assertOk()
            ->assertJsonPath('status', 'PAID_OUT')->assertJsonPath('status_label', 'Versé sur votre Mobile Money');
        $this->assertStringStartsWith('SIM-MG-PAY-', IncomingTransfer::find($id)->payout_reference);
        // Cashop n'a jamais détenu les fonds : aucune écriture comptable.
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->getJson('/api/v1/incoming', $this->headers)->assertJsonCount(1, 'data');
    }

    #[Test]
    public function un_transfert_ne_se_reclame_qu_une_fois(): void
    {
        $reference = $this->simulate();
        $key = (string) Str::uuid();

        $first = $this->claim($reference, key: $key)->assertCreated()->json('id');
        $this->assertSame($first, $this->claim($reference, key: $key)->assertOk()->json('id'), 'Rejeu idempotent');
        $this->claim($reference)->assertStatus(409)->assertJsonPath('code', 'ALREADY_CLAIMED');
        $this->assertSame(1, IncomingTransfer::count());
    }

    #[Test]
    public function reference_inconnue(): void
    {
        $this->postJson('/api/v1/incoming/lookup', ['network' => 'moneygram', 'reference' => 'SIM-IN-MG-INCONNU1'], $this->headers)
            ->assertNotFound()->assertJsonPath('code', 'INCOMING_NOT_FOUND');
    }

    #[Test]
    public function un_transfert_destine_a_quelqu_un_d_autre_est_refuse(): void
    {
        $other = ['Authorization' => 'Bearer '.$this->tokenFor('client2@cashop.test', 'device-2')];
        $reference = $this->simulate(headers: $other); // destiné au second client

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/incoming/lookup', ['network' => 'moneygram', 'reference' => $reference], $this->headers)
            ->assertStatus(403)->assertJsonPath('code', 'RECEIVER_MISMATCH')->assertJsonMissingPath('amount');
        $this->claim($reference)->assertStatus(403);
    }

    #[Test]
    public function versement_refuse_par_le_reseau(): void
    {
        $this->claim($this->simulate('PAYOUT_FAILED'))->assertCreated()
            ->assertJsonPath('status', 'FAILED')->assertJsonPath('failure.code', 'PAYOUT_REJECTED');
    }

    #[Test]
    public function versement_en_attente_reste_en_attente(): void
    {
        $id = $this->claim($this->simulate('PAYOUT_PENDING'))->json('id');

        $this->postJson("/api/v1/incoming/{$id}/refresh", [], $this->headers)->assertJsonPath('status', 'PAYOUT_PENDING');
    }

    #[Test]
    public function pin_errone(): void
    {
        $this->claim($this->simulate(), ['pin' => '000111'])->assertStatus(401)->assertJsonPath('code', 'PIN_INVALID');
        $this->assertSame(0, IncomingTransfer::count());
    }

    #[Test]
    public function numero_de_versement_congolais_exige(): void
    {
        $this->claim($this->simulate(), ['payout_msisdn' => '+33600000000'])->assertStatus(422)->assertJsonValidationErrors(['payout_msisdn']);
    }

    #[Test]
    public function identite_verifiee_exigee(): void
    {
        $reference = $this->simulate();
        User::where('email', 'client@cashop.test')->first()->kycProfile()->update(['status' => 'PENDING']);

        $this->postJson('/api/v1/incoming/lookup', ['network' => 'moneygram', 'reference' => $reference], $this->headers)
            ->assertStatus(403)->assertJsonPath('code', 'KYC_REQUIRED');
    }

    #[Test]
    public function la_simulation_n_existe_qu_en_demonstration(): void
    {
        Provider::query()->where('code', 'moneygram')->update(['mode' => 'sandbox']);

        $this->postJson('/api/v1/incoming/simulate', ['network' => 'moneygram', 'amount' => 75000], $this->headers)
            ->assertStatus(403)->assertJsonPath('code', 'SIMULATION_ONLY');
    }

    #[Test]
    public function receptions_inaccessibles_aux_autres_utilisateurs(): void
    {
        $id = $this->claim($this->simulate())->json('id');
        $other = ['Authorization' => 'Bearer '.$this->tokenFor('client2@cashop.test', 'device-2')];

        $this->getJson("/api/v1/incoming/{$id}", $other)->assertNotFound();
        $this->postJson("/api/v1/incoming/{$id}/refresh", [], $other)->assertNotFound();
        $this->getJson('/api/v1/incoming', $other)->assertJsonCount(0, 'data');
    }

    #[Test]
    public function le_numero_de_versement_est_chiffre(): void
    {
        $id = $this->claim($this->simulate())->json('id');

        $this->assertStringNotContainsString('050000009', DB::table('incoming_transfers')->where('id', $id)->value('payout_msisdn'));
    }
}
