<?php

namespace Tests\Feature\Transfers;

use App\Domain\Payments\Models\Transfer;
use App\Domain\Providers\Connectors\Simulated\SimulatedConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class WebhookTest extends TransferTestCase
{
    private function webhook(string $provider, array $body, ?int $timestamp = null, ?string $secret = null): TestResponse
    {
        $json = json_encode($body);
        $timestamp ??= now()->getTimestamp();
        $signature = SimulatedConnector::sign($secret ?? 'secret-de-test', $timestamp, $json);

        return $this->call('POST', "/api/v1/webhooks/{$provider}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_'.strtoupper(str_replace('-', '_', SimulatedConnector::SIGNATURE_HEADER)) => $signature,
            'HTTP_'.strtoupper(str_replace('-', '_', SimulatedConnector::TIMESTAMP_HEADER)) => (string) $timestamp,
        ], $json);
    }

    #[Test]
    public function une_notification_valide_fait_avancer_le_transfert(): void
    {
        $transfer = Transfer::find($this->send()->json('id'));
        $attempt = $transfer->collection()->first();

        $this->webhook('mtn_momo', ['event_id' => 'evt-1', 'reference' => $attempt->id])->assertOk()->assertJsonPath('status', 'received');

        $this->assertSame('TRANSFER_AVAILABLE_FOR_PICKUP', $transfer->fresh()->status->value);
        $this->assertSame('PROCESSED', DB::table('webhooks')->where('provider_event_id', 'evt-1')->value('status'));
    }

    #[Test]
    public function une_notification_repetee_n_est_traitee_qu_une_fois(): void
    {
        $transfer = Transfer::find($this->send()->json('id'));
        $body = ['event_id' => 'evt-2', 'reference' => $transfer->collection()->first()->id];

        $this->webhook('mtn_momo', $body)->assertOk()->assertJsonPath('status', 'received');
        $this->webhook('mtn_momo', $body)->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, DB::table('webhooks')->whereRaw('signature_valid')->count());
        $this->assertSame(1, DB::table('ledger_transactions')->where('type', 'TRANSFER_RESERVE')->count());
    }

    #[Test]
    public function une_signature_invalide_est_rejetee_et_journalisee(): void
    {
        $transfer = Transfer::find($this->send()->json('id'));

        $this->webhook('mtn_momo', ['event_id' => 'evt-3', 'reference' => $transfer->collection()->first()->id], secret: 'mauvais-secret')
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_WEBHOOK');

        $this->assertSame('PAYMENT_PENDING', $transfer->fresh()->status->value);
        $this->assertSame('REJECTED', DB::table('webhooks')->value('status'));
    }

    #[Test]
    public function une_notification_trop_ancienne_est_rejetee(): void
    {
        $this->webhook('mtn_momo', ['event_id' => 'evt-4', 'reference' => 'x'], timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(401);
    }

    #[Test]
    public function prestataire_inconnu(): void
    {
        $this->webhook('inconnu', ['event_id' => 'evt-5'])->assertNotFound();
    }
}
