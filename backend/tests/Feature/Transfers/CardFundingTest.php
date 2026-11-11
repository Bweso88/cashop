<?php

namespace Tests\Feature\Transfers;

use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class CardFundingTest extends TransferTestCase
{
    private function sendByCard(?string $scenario = null, array $extra = []): TestResponse
    {
        $quoteId = $this->quote(['funding_provider' => 'visa_direct'])->assertCreated()->json('id');

        return $this->postJson('/api/v1/transfers', array_filter([
            'quote_id' => $quoteId, 'beneficiary_id' => $this->beneficiary()->id, 'pin' => '482915', 'simulation_scenario' => $scenario, ...$extra,
        ]), $this->headers + ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function pay(string $actionUrl, string $outcome): TestResponse
    {
        $reference = basename($actionUrl);

        return $this->postJson("/api/v1/simulator/card-payments/{$reference}", ['outcome' => $outcome], $this->headers);
    }

    #[Test]
    public function la_carte_est_proposee_comme_moyen_de_paiement(): void
    {
        $this->getJson('/api/v1/catalog/funding-providers', $this->headers)->assertOk()
            ->assertJsonPath('data.2.code', 'visa_direct')->assertJsonPath('data.2.type', 'card')->assertJsonPath('data.0.type', 'mobile_money');
    }

    #[Test]
    public function paiement_par_carte_accepte_puis_transfert_livre(): void
    {
        $created = $this->sendByCard()->assertCreated()->assertJsonPath('status', 'PAYMENT_PENDING')
            ->assertJsonPath('funding.type', 'card')->assertJsonPath('funding.msisdn_masked', 'Carte Visa');
        $action = $created->json('funding.action_url');
        $this->assertMatchesRegularExpression('#^/paiement-carte/[0-9a-f-]{36}$#', $action);

        // Tant que le client n'a pas payé sur la page du prestataire, rien n'avance.
        $this->refresh($created->json('id'))->assertJsonPath('status', 'PAYMENT_PENDING');

        $this->getJson('/api/v1/simulator/card-payments/'.basename($action), $this->headers)->assertOk()
            ->assertJsonPath('amount.amount', 103000)->assertJsonPath('status', 'AWAITING_CUSTOMER')->assertJsonPath('is_simulation', true);
        $this->pay($action, 'APPROVED')->assertOk()->assertJsonPath('status', 'TRANSFER_AVAILABLE_FOR_PICKUP');

        $transfer = $this->getJson('/api/v1/transfers/'.$created->json('id'), $this->headers)
            ->assertJsonPath('funding.msisdn_masked', 'Carte •••• 4242')->assertJsonPath('funding.action_url', null);
        $this->assertStringStartsWith('SIM-VISA-', $transfer->json('funding.payment_reference'));
        $this->assertSame(103000, $this->balance('collection_clearing:visa_direct:XAF'));
    }

    #[Test]
    public function carte_refusee_aucun_debit(): void
    {
        $created = $this->sendByCard()->json();
        $this->pay($created['funding']['action_url'], 'DECLINED')->assertJsonPath('status', 'FAILED');

        $this->getJson("/api/v1/transfers/{$created['id']}", $this->headers)->assertJsonPath('failure.code', 'CARD_DECLINED');
        $this->assertSame(0, DB::table('ledger_entries')->count());
    }

    #[Test]
    public function echec_3d_secure(): void
    {
        $created = $this->sendByCard()->json();

        $this->pay($created['funding']['action_url'], 'AUTHENTICATION_FAILED')->assertJsonPath('status', 'FAILED');
        $this->assertSame('AUTHENTICATION_FAILED', Transfer::find($created['id'])->failure_code);
    }

    #[Test]
    public function transfert_refuse_rembourse_sur_la_carte(): void
    {
        $created = $this->sendByCard('TRANSFER_FAILED')->json();

        $this->pay($created['funding']['action_url'], 'APPROVED')->assertJsonPath('status', 'REFUNDED');
        $refund = PaymentAttempt::where('transfer_id', $created['id'])->where('direction', 'DISBURSEMENT')->sole();
        $this->assertStringStartsWith('SIM-VISA-RF-', $refund->provider_reference);
        $this->assertSame(0, $this->balance('collection_clearing:visa_direct:XAF'));
    }

    #[Test]
    public function cashop_refuse_toute_donnee_de_carte(): void
    {
        $this->sendByCard(extra: ['card_number' => '4111111111111111', 'cvv' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors(['card_number', 'cvv']);
        $this->assertSame(0, Transfer::count());

        $created = $this->sendByCard()->json();
        $this->postJson('/api/v1/simulator/card-payments/'.basename($created['funding']['action_url']), ['outcome' => 'APPROVED', 'card_number' => '4111111111111111'], $this->headers)
            ->assertStatus(422);
    }

    #[Test]
    public function pas_de_numero_mobile_money_pour_une_carte(): void
    {
        $this->sendByCard(extra: ['funding_msisdn' => '+242060000001'])->assertStatus(422)->assertJsonValidationErrors(['funding_msisdn']);
    }

    #[Test]
    public function la_page_de_paiement_d_un_autre_client_est_inaccessible(): void
    {
        $created = $this->sendByCard()->json();
        $other = ['Authorization' => 'Bearer '.$this->tokenFor('client2@cashop.test', 'device-2')];

        $this->postJson('/api/v1/simulator/card-payments/'.basename($created['funding']['action_url']), ['outcome' => 'APPROVED'], $other)->assertNotFound();
    }

    #[Test]
    public function la_page_simulee_n_existe_pas_pour_le_mobile_money(): void
    {
        $id = $this->send('PAYMENT_TIMEOUT')->json('id');
        $attempt = PaymentAttempt::where('transfer_id', $id)->sole();

        $this->postJson("/api/v1/simulator/card-payments/{$attempt->id}", ['outcome' => 'APPROVED'], $this->headers)->assertStatus(403);
    }

    #[Test]
    public function carte_indisponible_si_desactivee(): void
    {
        Provider::query()->where('code', 'visa_direct')->update(['is_enabled' => false]);

        $this->quote(['funding_provider' => 'visa_direct'])->assertStatus(422)->assertJsonPath('code', 'PROVIDER_UNAVAILABLE');
    }
}
