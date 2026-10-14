<?php

namespace App\Domain\Providers\Connectors\MtnMoMo;

use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP de la MoMo Open API (MTN). Sources : momodeveloper.mtn.com, pages « API User and API
 * Key Management », « Use Cases », « Callback », « Common Error Codes » (relevées le 2026-10-14).
 *
 * OFFICIEL : en-têtes Ocp-Apim-Subscription-Key (une clé par produit), Authorization Bearer,
 * X-Reference-Id (UUID v4), X-Target-Environment (sandbox, mtncongo…), X-Callback-Url ; jeton
 * OAuth 2.0 client credentials (RFC 6749) obtenu avec l'API User et l'API Key en Basic.
 *
 * NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED : chemin de l'opération de jeton (à lire dans
 * le Swagger « CreateAccessToken ») ; il n'a donc pas de valeur par défaut.
 *
 * Aucun secret (clé, API Key, jeton) n'est jamais journalisé.
 */
class MtnMoMoClient
{
    public const PRODUCTS = ['collection', 'disbursement'];

    public function __construct(private readonly array $config)
    {
        foreach (['base_url', 'target_environment', 'token_path'] as $key) {
            if (empty($config[$key])) {
                throw new ConnectorUnavailableException("MTN MoMo : configuration « {$key} » manquante (voir docs/providers/MTN-MOMO.md).");
            }
        }
        foreach (self::PRODUCTS as $product) {
            foreach (['subscription_key', 'api_user', 'api_key'] as $key) {
                if (empty($config[$product][$key])) {
                    throw new ConnectorUnavailableException("MTN MoMo : identifiant « {$product}.{$key} » manquant.");
                }
            }
        }
    }

    public function targetEnvironment(): string
    {
        return $this->config['target_environment'];
    }

    public function isSandbox(): bool
    {
        return $this->targetEnvironment() === 'sandbox';
    }

    public function callbackUrl(): ?string
    {
        return $this->config['callback_url'] ?: null;
    }

    public function post(string $product, string $path, string $referenceId, array $body): Response
    {
        $headers = ['X-Reference-Id' => $referenceId];
        if ($this->callbackUrl()) {
            $headers['X-Callback-Url'] = $this->callbackUrl();
        }

        return $this->send(fn () => $this->request($product)->withHeaders($headers)->post($path, $body));
    }

    public function get(string $product, string $path): Response
    {
        return $this->send(fn () => $this->request($product)->get($path));
    }

    /** Jeton réutilisé jusqu'à son expiration (recommandation MTN), renouvelé une minute avant. */
    public function token(string $product): string
    {
        $key = "mtn_momo:token:{$this->targetEnvironment()}:{$product}";

        return Cache::get($key) ?? $this->newToken($product, $key);
    }

    public function forgetToken(string $product): void
    {
        Cache::forget("mtn_momo:token:{$this->targetEnvironment()}:{$product}");
    }

    private function newToken(string $product, string $cacheKey): string
    {
        $path = str_replace('{product}', $product, $this->config['token_path']);
        $response = $this->send(fn () => $this->http($product)
            ->withBasicAuth($this->config[$product]['api_user'], $this->config[$product]['api_key'])
            ->post($path));

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            // Aucune opération n'a été tentée : refus définitif, sans détail sensible.
            throw new ConnectorException("MTN MoMo : jeton refusé (HTTP {$response->status()}).", definitive: true, failureCode: 'PROVIDER_AUTH_FAILED');
        }
        // Champs access_token / expires_in : RFC 6749 §5.1, à laquelle la documentation MTN renvoie.
        $ttl = max((int) ($response->json('expires_in') ?? 0) - 60, 30);
        Cache::put($cacheKey, $response->json('access_token'), $ttl);

        return $response->json('access_token');
    }

    private function request(string $product): PendingRequest
    {
        return $this->http($product)->withToken($this->token($product))
            ->withHeaders(['X-Target-Environment' => $this->targetEnvironment()]);
    }

    private function http(string $product): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->config[$product]['subscription_key']]);
    }

    /** Une coupure réseau ne dit rien du sort de l'opération : résultat inconnu, à vérifier. */
    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new ConnectorException('MTN MoMo injoignable : '.$e->getMessage(), definitive: false, failureCode: 'PROVIDER_UNREACHABLE');
        }
    }
}
