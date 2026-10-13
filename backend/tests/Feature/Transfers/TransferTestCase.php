<?php

namespace Tests\Feature\Transfers;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payments\Models\Beneficiary;
use App\Domain\Payments\Models\Transfer;
use App\Models\User;
use Database\Seeders\Development\DemoUserSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Auth\AuthTestCase;

abstract class TransferTestCase extends AuthTestCase
{
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        // Le simulateur répond immédiatement ; les tests de délai utilisent travel().
        config(['cashop.simulation.delay_seconds' => 0, 'cashop.simulation.webhook_secret' => 'secret-de-test']);
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->headers = ['Authorization' => 'Bearer '.$this->tokenFor('client@cashop.test')];
    }

    /** Sanctum mémorise l'utilisateur entre deux requêtes d'un même test : on l'oublie à chaque appel. */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $this->app['auth']->forgetGuards();

        return parent::json($method, $uri, $data, $headers, $options);
    }

    protected function client(): User
    {
        return User::where('email', 'client@cashop.test')->firstOrFail();
    }

    protected function beneficiary(string $country = 'FR'): Beneficiary
    {
        return Beneficiary::query()->where('user_id', $this->client()->id)->where('country_code', $country)->firstOrFail();
    }

    protected function quote(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/transfers/quote', array_merge([
            'network' => 'western_union',
            'funding_provider' => 'mtn_momo',
            'destination_country' => 'FR',
            'payout_method' => 'cash_pickup',
            'send_amount' => 100000,
        ], $overrides), $this->headers);
    }

    /** Devis puis envoi ; renvoie la réponse de création. */
    protected function send(?string $scenario = null, array $quote = [], array $overrides = [], ?string $key = null): TestResponse
    {
        $quoteId = $this->quote($quote)->assertCreated()->json('id');
        $country = $quote['destination_country'] ?? 'FR';

        return $this->postJson('/api/v1/transfers', array_filter(array_merge([
            'quote_id' => $quoteId,
            'beneficiary_id' => $this->beneficiary($country)->id,
            'funding_msisdn' => '+242060000001',
            'pin' => DemoUserSeeder::DEMO_PIN,
            'simulation_scenario' => $scenario,
        ], $overrides)), $this->headers + ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    protected function refresh(string $id): TestResponse
    {
        return $this->postJson("/api/v1/transfers/{$id}/refresh", [], $this->headers)->assertOk();
    }

    protected function statuses(string $id): array
    {
        return Transfer::findOrFail($id)->events()->get()->map(fn ($e) => $e->to_status->value)->all();
    }

    protected function balance(string $code): int
    {
        return app(LedgerService::class)->balance($code);
    }
}
