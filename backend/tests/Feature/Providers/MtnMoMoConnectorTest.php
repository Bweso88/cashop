<?php

namespace Tests\Feature\Providers;

use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\Exceptions\InvalidWebhookException;
use App\Domain\Providers\Connectors\MtnMoMo\MtnMoMoConnector;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use Database\Seeders\Reference\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comportement du connecteur face aux réponses documentées par MTN (Http::fake : aucun appel réel).
 * Les tests sur la vraie sandbox se font avec « php artisan cashop:mtn:sandbox-check ».
 */
class MtnMoMoConnectorTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://sandbox.momodeveloper.mtn.com';

    private const REF = '0b8f6f0e-6c1a-4d8a-9a43-2f4a7d4c9e11';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashop.mtn_momo' => [
            'base_url' => self::BASE,
            'target_environment' => 'sandbox',
            'token_path' => '/{product}/token-de-test/',
            'callback_url' => 'https://api.cashop.test/api/v1/webhooks/mtn_momo/s3cr3t',
            'callback_secret' => 's3cr3t',
            'timeout' => 5,
            'collection' => ['subscription_key' => 'sub-col', 'api_user' => 'user-col', 'api_key' => 'key-col'],
            'disbursement' => ['subscription_key' => 'sub-dis', 'api_user' => 'user-dis', 'api_key' => 'key-dis'],
        ]]);
        Http::preventStrayRequests();
    }

    private function fake(array $responses): void
    {
        Http::fake([
            self::BASE.'/*/token-de-test/' => Http::response(['access_token' => 'jeton', 'token_type' => 'access_token', 'expires_in' => 3600]),
            ...$responses,
        ]);
    }

    private function connector(): MtnMoMoConnector
    {
        return new MtnMoMoConnector(ProviderMode::Sandbox);
    }

    private function collect(string $msisdn = '+46733000001', string $description = 'Cashop CSH-AB12-CD34'): ConnectorResult
    {
        return $this->connector()->collect(new CollectionRequest(self::REF, $msisdn, 103000, 'XAF', $description));
    }

    #[Test]
    public function demande_de_debit_conforme_a_la_documentation(): void
    {
        $this->fake([self::BASE.'/collection/v1_0/requesttopay' => Http::response(null, 202)]);

        $result = $this->collect();

        $this->assertSame(ConnectorStatus::Pending, $result->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/collection/token-de-test/')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('user-col:key-col'))
            && $r->hasHeader('Ocp-Apim-Subscription-Key', 'sub-col'));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::BASE.'/collection/v1_0/requesttopay'
            && $r->hasHeader('Authorization', 'Bearer jeton')
            && $r->hasHeader('X-Reference-Id', self::REF)
            && $r->hasHeader('X-Target-Environment', 'sandbox')
            && $r->hasHeader('X-Callback-Url', 'https://api.cashop.test/api/v1/webhooks/mtn_momo/s3cr3t')
            && $r['amount'] === '103000'
            && $r['currency'] === 'EUR' // seule devise de la sandbox
            && $r['externalId'] === self::REF
            && $r['payer'] === ['partyIdType' => 'MSISDN', 'partyId' => '46733000001']);
    }

    #[Test]
    public function le_jeton_est_reutilise(): void
    {
        $this->fake([self::BASE.'/collection/v1_0/requesttopay*' => Http::response(['status' => 'PENDING'], 202)]);

        $this->collect();
        $this->connector()->collectionStatus(self::REF);

        Http::assertSentCount(3); // 1 jeton + 2 opérations
    }

    #[Test]
    public function message_sans_apostrophe_ni_accent_et_limite_a_160_caracteres(): void
    {
        $this->assertSame('Cashop envoi a N Goma - Pointe-Noire', MtnMoMoConnector::message("Cashop envoi à N'Goma — Pointe-Noire"));
        $this->assertSame(160, mb_strlen(MtnMoMoConnector::message(str_repeat('a', 300))));
    }

    #[Test]
    public function reference_deja_utilisee_relit_le_statut_sans_nouvelle_demande(): void
    {
        $this->fake([
            self::BASE.'/collection/v1_0/requesttopay' => Http::response(['code' => 'RESOURCE_ALREADY_EXIST'], 409),
            self::BASE.'/collection/v1_0/requesttopay/'.self::REF => Http::response(['status' => 'SUCCESSFUL', 'financialTransactionId' => '123456']),
        ]);

        $result = $this->collect();

        $this->assertSame(ConnectorStatus::Confirmed, $result->status);
        $this->assertSame('123456', $result->providerReference);
    }

    #[Test]
    public function statuts_documentes(): void
    {
        $this->fake([self::BASE.'/collection/v1_0/requesttopay/*' => Http::sequence()
            ->push(['status' => 'PENDING'])
            ->push(['status' => 'SUCCESSFUL'])
            ->push(['status' => 'FAILED', 'reason' => 'APPROVAL_REJECTED'])
            ->push(['status' => 'QUELQUE_CHOSE'])
            ->push([])
            ->push(['code' => 'RESOURCE_NOT_FOUND'], 404)]);
        $status = fn () => $this->connector()->collectionStatus(self::REF);

        $this->assertSame(ConnectorStatus::Pending, $status()->status);
        $this->assertSame(ConnectorStatus::Confirmed, $status()->status);
        $failed = $status();
        $this->assertSame(ConnectorStatus::Failed, $failed->status);
        $this->assertSame('APPROVAL_REJECTED', $failed->failureCode);
        $this->assertSame('Paiement refusé par le titulaire du compte.', $failed->failureReason);
        $this->assertSame(ConnectorStatus::Unknown, $status()->status, 'Jamais un succès par défaut');
        $this->assertSame(ConnectorStatus::Unknown, $status()->status);
        $notFound = $status();
        $this->assertSame(ConnectorStatus::Failed, $notFound->status);
        $this->assertSame('NOT_FOUND', $notFound->failureCode);
    }

    #[Test]
    public function refus_explicites_definitifs(): void
    {
        foreach ([[400, null], [401, null], [403, 'FORBIDDEN_IP'], [500, 'PAYER_NOT_FOUND'], [500, 'INVALID_CURRENCY'], [500, 'NOT_ALLOWED_TARGET_ENVIRONMENT']] as [$http, $code]) {
            $this->fake([self::BASE.'/collection/v1_0/requesttopay' => Http::response($code ? ['code' => $code] : null, $http)]);
            try {
                $this->collect();
                $this->fail("HTTP {$http} aurait dû lever une exception");
            } catch (ConnectorException $e) {
                $this->assertTrue($e->definitive, "HTTP {$http} {$code}");
            }
        }
    }

    #[Test]
    public function erreurs_ambigues_jamais_conclues(): void
    {
        foreach ([[500, 'INTERNAL_PROCESSING_ERROR'], [503, 'SERVICE_UNAVAILABLE'], [500, null], [502, null]] as [$http, $code]) {
            $this->fake([self::BASE.'/collection/v1_0/requesttopay' => Http::response($code ? ['code' => $code] : null, $http)]);
            try {
                $this->collect();
                $this->fail("HTTP {$http} aurait dû lever une exception");
            } catch (ConnectorException $e) {
                $this->assertFalse($e->definitive, "HTTP {$http} {$code} : résultat inconnu");
            }
        }
    }

    #[Test]
    public function coupure_reseau_resultat_inconnu(): void
    {
        $this->fake([self::BASE.'/collection/v1_0/requesttopay' => fn () => throw new ConnectionException('timeout')]);

        try {
            $this->collect();
            $this->fail('Exception attendue');
        } catch (ConnectorException $e) {
            $this->assertFalse($e->definitive);
            $this->assertSame('PROVIDER_UNREACHABLE', $e->failureCode);
        }
    }

    #[Test]
    public function remboursement_par_disbursement(): void
    {
        $this->fake([
            self::BASE.'/disbursement/v1_0/transfer' => Http::response(null, 202),
            self::BASE.'/disbursement/v1_0/transfer/*' => Http::response(['status' => 'FAILED', 'reason' => 'NOT_ENOUGH_FUNDS']),
        ]);

        $created = $this->connector()->disburse(new DisbursementRequest(self::REF, '+242060000001', 103000, 'XAF', 'Remboursement Cashop'));
        $status = $this->connector()->disbursementStatus(self::REF);

        $this->assertSame(ConnectorStatus::Pending, $created->status);
        $this->assertSame('Fonds insuffisants.', $status->failureReason);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::BASE.'/disbursement/v1_0/transfer'
            && $r->hasHeader('Ocp-Apim-Subscription-Key', 'sub-dis')
            && $r['payee'] === ['partyIdType' => 'MSISDN', 'partyId' => '242060000001']);
    }

    #[Test]
    public function jeton_refuse(): void
    {
        Http::fake([self::BASE.'/*/token-de-test/' => Http::response(['error' => 'invalid_client'], 401)]);

        try {
            $this->collect();
            $this->fail('Exception attendue');
        } catch (ConnectorException $e) {
            $this->assertTrue($e->definitive);
            $this->assertSame('PROVIDER_AUTH_FAILED', $e->failureCode);
            $this->assertStringNotContainsString('key-col', $e->getMessage());
        }
    }

    #[Test]
    public function configuration_incomplete_connecteur_indisponible(): void
    {
        config(['cashop.mtn_momo.token_path' => null]);

        $this->expectException(ConnectorUnavailableException::class);
        $this->connector();
    }

    #[Test]
    public function sandbox_et_production_ne_se_melangent_pas(): void
    {
        $this->expectException(ConnectorUnavailableException::class);
        new MtnMoMoConnector(ProviderMode::Production); // cible « sandbox » configurée
    }

    #[Test]
    public function notification_acceptee_seulement_avec_le_secret_de_l_url(): void
    {
        $request = fn (string $secret) => tap(Request::create('/api/v1/webhooks/mtn_momo/'.$secret, 'PUT', content: json_encode(['externalId' => self::REF, 'status' => 'SUCCESSFUL'])), function ($r) {
            $r->headers->set('Content-Type', 'application/json');
            $route = (new Route('PUT', 'api/v1/webhooks/{provider}/{secret?}', []))->bind($r);
            $r->setRouteResolver(fn () => $route);
        });

        $this->assertSame(self::REF, $this->connector()->verifyWebhook($request('s3cr3t'))->reference);
        $this->expectException(InvalidWebhookException::class);
        $this->connector()->verifyWebhook($request('mauvais'));
    }

    #[Test]
    public function le_registre_fournit_le_connecteur_officiel_en_sandbox(): void
    {
        $this->seed(ProviderSeeder::class);
        Provider::query()->where('code', 'mtn_momo')->update(['mode' => 'sandbox']);

        $this->assertInstanceOf(MtnMoMoConnector::class, app(ConnectorRegistry::class)->wallet('mtn_momo', ProviderMode::Sandbox));
    }
}
