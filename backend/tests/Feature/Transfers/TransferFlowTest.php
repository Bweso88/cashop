<?php

namespace Tests\Feature\Transfers;

use App\Domain\Payments\Models\PaymentAttempt;
use App\Domain\Payments\Models\Receipt;
use App\Domain\Payments\Models\ReconciliationRecord;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Providers\Connectors\Simulated\SimulationStore;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class TransferFlowTest extends TransferTestCase
{
    #[Test]
    public function envoi_reussi_retrait_en_agence_avec_recu(): void
    {
        $created = $this->send()->assertCreated()
            ->assertJsonPath('status', 'PAYMENT_PENDING')
            ->assertJsonPath('funding.payment_status', 'PENDING')
            ->assertJsonPath('is_simulation', true);
        $id = $created->json('id');
        $this->assertMatchesRegularExpression('/^CSH-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $created->json('external_reference'));

        $transfer = $this->refresh($id)
            ->assertJsonPath('status', 'TRANSFER_AVAILABLE_FOR_PICKUP')
            ->assertJsonPath('funding.payment_status', 'CONFIRMED')
            ->assertJsonPath('is_delivered', true)
            ->json();

        // Références simulées : jamais confondables avec de vraies références réseau.
        $this->assertStringStartsWith('SIM-WU-', $transfer['provider_reference']);
        $this->assertStringStartsWith('SIM-', $transfer['pickup_code']);
        $this->assertStringContainsString('aucun fonds réel versé', $transfer['simulation_notice']);
        $this->assertSame(['INITIATED', 'QUOTE_CONFIRMED', 'PAYMENT_PENDING', 'PAYMENT_CONFIRMED', 'TRANSFER_AVAILABLE_FOR_PICKUP'], $this->statuses($id));

        $receipt = $this->getJson("/api/v1/transfers/{$id}/receipt", $this->headers)->assertOk()->json('data.0');
        $this->assertSame('TRANSFER', $receipt['kind']);
        $this->assertTrue($receipt['is_simulation']);
        $this->assertSame(103000, $receipt['content']['total_debit']);
        $this->assertSame('Jean Dupont', $receipt['content']['beneficiary']);

        // Ledger : le client n'est plus créancier, le réseau et les frais Cashop sont constatés.
        $this->assertSame(0, $this->balance('in_flight:XAF'));
        $this->assertSame(103000, $this->balance('collection_clearing:mtn_momo:XAF'));
        $this->assertSame(102000, $this->balance('remittance_payable:western_union:XAF'));
        $this->assertSame(1000, $this->balance('fee_revenue:XAF'));
    }

    #[Test]
    public function versement_sur_portefeuille_mobile(): void
    {
        $id = $this->send(quote: ['destination_country' => 'CM', 'payout_method' => 'mobile_money'])->assertCreated()->json('id');

        $this->refresh($id)->assertJsonPath('status', 'PAYOUT_CONFIRMED')->assertJsonPath('receive.currency', 'XAF');
        $this->assertSame(['INITIATED', 'QUOTE_CONFIRMED', 'PAYMENT_PENDING', 'PAYMENT_CONFIRMED', 'TRANSFER_CONFIRMED', 'PAYOUT_CONFIRMED'], $this->statuses($id));
    }

    #[Test]
    public function paiement_refuse_aucun_debit_aucun_remboursement(): void
    {
        $id = $this->send('PAYMENT_REFUSED')->json('id');

        $this->refresh($id)->assertJsonPath('status', 'FAILED')->assertJsonPath('failure.code', 'PAYER_REJECTED')->assertJsonPath('funding.payment_status', 'FAILED');
        $this->assertSame(0, Refund::count());
        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->getJson("/api/v1/transfers/{$id}/receipt", $this->headers)->assertStatus(409)->assertJsonPath('code', 'RECEIPT_NOT_AVAILABLE');
    }

    #[Test]
    public function scenario_critique_paiement_confirme_puis_transfert_refuse(): void
    {
        $id = $this->send('TRANSFER_FAILED')->json('id');

        $transfer = $this->refresh($id)->assertJsonPath('status', 'REFUNDED')->json();

        $this->assertSame(['INITIATED', 'QUOTE_CONFIRMED', 'PAYMENT_PENDING', 'PAYMENT_CONFIRMED', 'REFUND_PENDING', 'REFUNDED'], $this->statuses($id));
        // Un seul débit, une seule restitution, du montant total (frais compris).
        $this->assertSame(1, PaymentAttempt::where('transfer_id', $id)->where('direction', 'COLLECTION')->count());
        $refundAttempt = PaymentAttempt::where('transfer_id', $id)->where('direction', 'DISBURSEMENT')->sole();
        $this->assertSame(103000, $refundAttempt->amount);
        $this->assertSame('+242060000001', $refundAttempt->account);
        $refund = Refund::where('transfer_id', $id)->sole();
        $this->assertSame('COMPLETED', $refund->status->value);
        $this->assertNull($refund->requested_by, 'Remboursement déclenché par le système');
        // Preuves : motif conservé, journal d'audit, reçu de remboursement.
        $this->assertSame('TRANSFER_REJECTED', $transfer['failure']['code']);
        $this->assertSame(['transfer.PAYMENT_CONFIRMED', 'transfer.REFUND_PENDING', 'transfer.REFUNDED'],
            DB::table('audit_logs')->where('subject_id', $id)->orderBy('id')->pluck('action')->all());
        $this->getJson("/api/v1/transfers/{$id}/receipt", $this->headers)->assertOk()->assertJsonPath('data.0.kind', 'REFUND');
        // Ledger : les fonds sont ressortis, plus rien n'est dû ni conservé.
        $this->assertSame(0, $this->balance('in_flight:XAF'));
        $this->assertSame(0, $this->balance('collection_clearing:mtn_momo:XAF'));
        $this->assertSame(0, $this->balance('fee_revenue:XAF'));
    }

    #[Test]
    public function delai_de_paiement_depasse_puis_rapprochement(): void
    {
        $id = $this->send('PAYMENT_TIMEOUT')->json('id');
        $this->refresh($id)->assertJsonPath('status', 'PAYMENT_PENDING');

        $this->travel(config('cashop.transfers.payment_timeout_minutes') + 1)->minutes();
        $this->refresh($id)->assertJsonPath('status', 'REQUIRES_RECONCILIATION')->assertJsonPath('failure.code', 'PAYMENT_TIMEOUT');

        // Le rapprochement constate que l'opérateur n'a toujours pas conclu : rien n'est inventé.
        $record = ReconciliationRecord::where('transfer_id', $id)->latest('created_at')->first();
        $this->assertSame('STILL_UNKNOWN', $record->outcome);
        $this->assertSame('PENDING', $record->payment_status_seen);
        $this->assertSame(1, PaymentAttempt::where('transfer_id', $id)->count(), 'Aucune seconde demande de débit');
    }

    #[Test]
    public function reponse_operateur_inconnue_jamais_traitee_comme_un_succes(): void
    {
        $id = $this->send('PAYMENT_UNKNOWN_RESPONSE')->json('id');

        $this->refresh($id)->assertJsonPath('status', 'REQUIRES_RECONCILIATION')->assertJsonPath('failure.code', 'PAYMENT_UNKNOWN_RESPONSE');
        $this->assertSame(0, DB::table('ledger_entries')->count());
    }

    #[Test]
    public function delai_a_la_creation_du_transfert_retrouve_par_rapprochement_sans_doublon(): void
    {
        $id = $this->send('TRANSFER_TIMEOUT')->json('id');

        $transfer = $this->refresh($id)->assertJsonPath('status', 'TRANSFER_AVAILABLE_FOR_PICKUP')->json();

        $this->assertSame(['INITIATED', 'QUOTE_CONFIRMED', 'PAYMENT_PENDING', 'PAYMENT_CONFIRMED', 'REQUIRES_RECONCILIATION', 'TRANSFER_AVAILABLE_FOR_PICKUP'], $this->statuses($id));
        $record = ReconciliationRecord::where('transfer_id', $id)->sole();
        $this->assertSame('RESOLVED', $record->outcome);
        $this->assertSame('AVAILABLE_FOR_PICKUP', $record->transfer_status_seen);
        // La référence est celle du transfert créé initialement : il n'a pas été recréé.
        $simulated = app(SimulationStore::class)->get('western_union', $transfer['external_reference']);
        $this->assertSame($simulated['provider_reference'], $transfer['provider_reference']);
    }

    #[Test]
    public function transfert_en_attente_reste_en_attente(): void
    {
        $id = $this->send('TRANSFER_PENDING')->json('id');

        $this->refresh($id)->assertJsonPath('status', 'TRANSFER_PENDING')->assertJsonPath('is_delivered', false);
        $this->refresh($id)->assertJsonPath('status', 'TRANSFER_PENDING');
        $this->assertSame(0, Receipt::count());
    }

    #[Test]
    public function double_soumission_un_seul_transfert_un_seul_debit(): void
    {
        $quoteId = $this->quote()->json('id');
        $payload = ['quote_id' => $quoteId, 'beneficiary_id' => $this->beneficiary()->id, 'funding_msisdn' => '+242060000001', 'pin' => '482915'];
        $headers = $this->headers + ['Idempotency-Key' => 'cle-unique-123'];

        $first = $this->postJson('/api/v1/transfers', $payload, $headers)->assertCreated()->json('id');
        $second = $this->postJson('/api/v1/transfers', $payload, $headers)->assertOk()->json('id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Transfer::count());
        $this->assertSame(1, PaymentAttempt::count());
    }

    #[Test]
    public function un_devis_ne_sert_qu_une_fois(): void
    {
        $quoteId = $this->quote()->json('id');
        $payload = ['quote_id' => $quoteId, 'beneficiary_id' => $this->beneficiary()->id, 'funding_msisdn' => '+242060000001', 'pin' => '482915'];

        $this->postJson('/api/v1/transfers', $payload, $this->headers + ['Idempotency-Key' => 'cle-a-0001'])->assertCreated();
        $this->postJson('/api/v1/transfers', $payload, $this->headers + ['Idempotency-Key' => 'cle-b-0002'])->assertStatus(409)->assertJsonPath('code', 'QUOTE_EXPIRED');
    }

    #[Test]
    public function devis_expire(): void
    {
        $quoteId = $this->quote()->json('id');
        $this->travel(16)->minutes();

        $this->postJson('/api/v1/transfers', ['quote_id' => $quoteId, 'beneficiary_id' => $this->beneficiary()->id, 'funding_msisdn' => '+242060000001', 'pin' => '482915'],
            $this->headers + ['Idempotency-Key' => 'cle-expiree-01'])->assertStatus(409)->assertJsonPath('code', 'QUOTE_EXPIRED');
    }

    #[Test]
    public function pin_errone_aucun_transfert(): void
    {
        $this->send(overrides: ['pin' => '111222'])->assertStatus(401)->assertJsonPath('code', 'PIN_INVALID');

        $this->assertSame(0, Transfer::count());
        $this->assertSame(0, PaymentAttempt::count());
    }

    #[Test]
    public function beneficiaire_d_un_autre_pays_refuse(): void
    {
        $this->send(overrides: ['beneficiary_id' => $this->beneficiary('CM')->id])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function informations_beneficiaire_exigees_par_le_reseau(): void
    {
        $this->beneficiary('CM')->update(['phone_e164' => null]);

        $this->send(quote: ['destination_country' => 'CM', 'payout_method' => 'mobile_money'])
            ->assertStatus(422)->assertJsonValidationErrors(['phone_e164']);
        $this->assertSame(0, PaymentAttempt::count(), 'Aucun débit tant que le transfert n’est pas valide');
    }

    #[Test]
    public function acces_refuse_aux_transferts_d_un_autre_utilisateur(): void
    {
        $id = $this->send()->json('id');
        $other = ['Authorization' => 'Bearer '.$this->tokenFor('client2@cashop.test', 'device-2')];

        $this->getJson("/api/v1/transfers/{$id}", $other)->assertNotFound();
        $this->postJson("/api/v1/transfers/{$id}/refresh", [], $other)->assertNotFound();
        $this->getJson("/api/v1/transfers/{$id}/receipt", $other)->assertNotFound();
        $this->getJson('/api/v1/transfers', $other)->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function isolation_des_environnements_aucune_bascule_automatique(): void
    {
        $id = $this->send()->json('id');
        // Le réseau passe en sandbox après la création : l'opération simulée n'y est pas transmise.
        Provider::query()->where('code', 'western_union')->update(['mode' => 'sandbox']);

        $this->refresh($id)->assertJsonPath('status', 'PAYMENT_CONFIRMED')->assertJsonPath('environment', 'mock');
        $this->assertNull(Transfer::find($id)->submitted_at);
    }

    #[Test]
    public function scenario_de_simulation_interdit_hors_demonstration(): void
    {
        $id = $this->send('TRANSFER_PENDING')->json('id');

        // Une opération ne peut pas porter de scénario simulé hors de l'environnement de démonstration.
        $this->expectException(QueryException::class);
        DB::table('transfers')->where('id', $id)->update(['environment' => 'sandbox']);
    }

    #[Test]
    public function historique_filtres_et_recherche_par_reference(): void
    {
        $done = $this->send()->json('id');
        $this->refresh($done);
        $pending = $this->send('TRANSFER_PENDING')->json('id');
        $this->refresh($pending);
        $reference = Transfer::find($done)->external_reference;

        $this->getJson('/api/v1/transfers?state=completed', $this->headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $done);
        $this->getJson('/api/v1/transfers?state=in_progress', $this->headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending);
        $this->getJson('/api/v1/transfers?q='.strtolower(substr($reference, 4)), $this->headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $done);
    }

    #[Test]
    public function la_tache_planifiee_fait_avancer_les_transferts(): void
    {
        $id = $this->send()->json('id');

        $this->artisan('cashop:transfers:advance')->assertSuccessful();

        $this->assertSame('TRANSFER_AVAILABLE_FOR_PICKUP', Transfer::find($id)->status->value);
    }

    #[Test]
    public function un_utilisateur_non_verifie_ne_peut_pas_envoyer(): void
    {
        User::where('email', 'client@cashop.test')->first()->kycProfile()->update(['status' => 'PENDING']);

        $this->quote()->assertStatus(403)->assertJsonPath('code', 'KYC_REQUIRED');
    }
}
