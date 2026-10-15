<?php

namespace App\Domain\Providers\Connectors\MtnMoMo;

use App\Domain\Providers\Connectors\Contracts\WalletConnector;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\ConnectorResult;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Connectors\DTO\WebhookNotification;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\Exceptions\InvalidWebhookException;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ConnectorStatus;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Connecteur officiel MTN Mobile Money (MoMo Open API) : débit du client (Collection →
 * Request to Pay) et remboursement (Disbursement → Transfer).
 *
 * OFFICIEL (pages du portail + définitions OpenAPI Collection / Disbursement) :
 *  - POST {base}/collection/v1_0/requesttopay et POST {base}/disbursement/v1_0/transfer,
 *    corps { amount (chaîne), currency, externalId, payer|payee { partyIdType: MSISDN, partyId },
 *    payerMessage, payeeNote } ; réponse 202 ;
 *  - GET …/requesttopay/{referenceId} et GET …/transfer/{referenceId} : 200 avec status
 *    (PENDING | SUCCESSFUL | FAILED), financialTransactionId, reason { code, message } ;
 *    un échec est renvoyé en 200 ; 404 RESOURCE_NOT_FOUND = opération inexistante ;
 *  - erreurs : corps ErrorReason { code, message } ; 409 RESOURCE_ALREADY_EXIST ;
 *  - messages : 160 caractères maximum, pas d'apostrophe ; numéro E.164 avec indicatif pays ;
 *  - notification envoyée une seule fois, sans signature décrite : simple signal.
 *
 * NOT CONFIRMED — à confirmer avec MTN Congo : numéro sans « + », devise XAF, URL de production.
 */
class MtnMoMoConnector implements WalletConnector
{
    public const CODE = 'mtn_momo';

    private const MESSAGE_MAX = 160;

    /** Refus explicites : la demande n'a pas été créée, aucun débit. */
    private const DEFINITIVE_ERRORS = [
        'PAYER_NOT_FOUND', 'PAYEE_NOT_FOUND', 'NOT_ALLOWED', 'NOT_ALLOWED_TARGET_ENVIRONMENT',
        'INVALID_CALLBACK_URL_HOST', 'INVALID_CURRENCY', 'NOT_ENOUGH_FUNDS', 'PAYER_LIMIT_REACHED',
        'PAYEE_NOT_ALLOWED_TO_RECEIVE',
    ];

    private readonly MtnMoMoClient $client;

    public function __construct(private readonly ProviderMode $environment, ?MtnMoMoClient $client = null)
    {
        $this->client = $client ?? new MtnMoMoClient((array) config('cashop.mtn_momo'));
        $production = $environment === ProviderMode::Production;
        if ($production === $this->client->isSandbox()) {
            throw new ConnectorUnavailableException(
                "MTN MoMo : X-Target-Environment « {$this->client->targetEnvironment()} » incompatible avec l'environnement Cashop {$environment->value}."
            );
        }
    }

    public function providerCode(): string
    {
        return self::CODE;
    }

    public function environment(): ProviderMode
    {
        return $this->environment;
    }

    public function capabilities(): array
    {
        return [ConnectorCapability::Collect, ConnectorCapability::Disburse, ConnectorCapability::Status, ConnectorCapability::Refund, ConnectorCapability::Webhook, ConnectorCapability::Reconcile];
    }

    public function supports(ConnectorCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /** Aucune opération de santé n'est documentée : on vérifie qu'un jeton peut être obtenu. */
    public function health(): bool
    {
        try {
            $this->client->token('collection');

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function collect(CollectionRequest $request): ConnectorResult
    {
        return $this->create('collection', '/collection/v1_0/requesttopay', $request->reference, 'payer', $request->msisdn, $request->amount, $request->currency, $request->description)
            ?? $this->collectionStatus($request->reference);
    }

    public function collectionStatus(string $reference): ConnectorResult
    {
        return $this->status('collection', "/collection/v1_0/requesttopay/{$reference}");
    }

    public function disburse(DisbursementRequest $request): ConnectorResult
    {
        return $this->create('disbursement', '/disbursement/v1_0/transfer', $request->reference, 'payee', $request->msisdn, $request->amount, $request->currency, $request->description)
            ?? $this->disbursementStatus($request->reference);
    }

    public function disbursementStatus(string $reference): ConnectorResult
    {
        return $this->status('disbursement', "/disbursement/v1_0/transfer/{$reference}");
    }

    /**
     * La notification MTN n'est pas signée (aucun mécanisme documenté) : elle est acceptée sur une
     * URL contenant un secret propre à Cashop, et ne sert qu'à déclencher une relecture du statut.
     */
    public function verifyWebhook(Request $request): WebhookNotification
    {
        $expected = (string) config('cashop.mtn_momo.callback_secret');
        if ($expected === '' || ! hash_equals($expected, (string) $request->route('secret'))) {
            throw new InvalidWebhookException('Secret de l’URL de notification invalide.');
        }
        $reference = $request->json('externalId');
        if (! is_string($reference) || $reference === '') {
            throw new InvalidWebhookException('Notification sans référence exploitable.');
        }

        return new WebhookNotification(eventId: null, reference: $reference, occurredAt: now());
    }

    /** @return ConnectorResult|null null si la référence existe déjà chez MTN (statut à relire). */
    private function create(string $product, string $path, string $reference, string $party, string $msisdn, int $amount, string $currency, string $description): ?ConnectorResult
    {
        $message = self::message($description);
        $response = $this->client->post($product, $path, $reference, [
            'amount' => (string) $amount,
            'currency' => $this->currency($currency),
            // La référence Cashop est renvoyée dans la notification : elle permet de retrouver l'opération.
            'externalId' => $reference,
            $party => ['partyIdType' => 'MSISDN', 'partyId' => self::partyId($msisdn)],
            'payerMessage' => $message,
            'payeeNote' => $message,
        ]);

        if ($response->status() === 202) {
            return new ConnectorResult(ConnectorStatus::Pending, raw: ['http_status' => 202]);
        }
        if ($response->status() === 409) {
            return null; // déjà reçue par MTN : jamais de seconde demande
        }

        throw $this->error($response);
    }

    private function status(string $product, string $path): ConnectorResult
    {
        $response = $this->client->get($product, $path);

        if ($response->status() === 404) {
            return new ConnectorResult(ConnectorStatus::Failed, failureCode: 'NOT_FOUND', failureReason: 'Opération inconnue de MTN MoMo : aucun débit n’a eu lieu.', raw: ['http_status' => 404]);
        }
        if (! $response->successful()) {
            throw $this->error($response, forStatus: true);
        }

        $reference = $response->json('financialTransactionId');
        $raw = ['http_status' => $response->status(), 'status' => $response->json('status'), 'reason' => $response->json('reason')];

        return match ($response->json('status')) {
            'PENDING' => new ConnectorResult(ConnectorStatus::Pending, $reference, raw: $raw),
            'SUCCESSFUL' => new ConnectorResult(ConnectorStatus::Confirmed, $reference, raw: $raw),
            'FAILED' => new ConnectorResult(ConnectorStatus::Failed, $reference, failureCode: self::reasonCode($response), failureReason: self::reasonLabel(self::reasonCode($response)), raw: $raw),
            // Statut absent ou inattendu : jamais interprété comme un succès.
            default => new ConnectorResult(ConnectorStatus::Unknown, $reference, raw: $raw),
        };
    }

    private function error(Response $response, bool $forStatus = false): ConnectorException
    {
        $code = self::errorCode($response);
        $http = $response->status();

        if ($http === 401) {
            $this->client->forgetToken('collection');
            $this->client->forgetToken('disbursement');
        }
        // Lors d'une création, ces réponses signifient que la demande n'a pas été acceptée.
        $definitive = ! $forStatus && (
            in_array($http, [400, 401, 403], true)
            || ($http === 500 && in_array($code, self::DEFINITIVE_ERRORS, true))
        );

        return new ConnectorException(
            "MTN MoMo : HTTP {$http}".($code ? " {$code}" : ''),
            definitive: $definitive,
            failureCode: $code ?? "HTTP_{$http}",
        );
    }

    /** En sandbox, MTN n'accepte que l'EUR (page « Sandbox Use Cases ») : montant de test uniquement. */
    private function currency(string $currency): string
    {
        return $this->client->isSandbox() ? 'EUR' : $currency;
    }

    /** Numéro avec indicatif pays, sans « + » (forme des exemples officiels). */
    public static function partyId(string $msisdn): string
    {
        return ltrim(preg_replace('/\s+/', '', $msisdn), '+');
    }

    /** Pas d'apostrophe ni de caractère spécial, 160 caractères au plus (Common Error Codes, 400). */
    public static function message(string $text): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $clean = trim(preg_replace(['/[^A-Za-z0-9 .,:\-]/', '/-{2,}/', '/\s+/'], [' ', '-', ' '], $ascii));

        return mb_substr($clean, 0, self::MESSAGE_MAX);
    }

    private static function errorCode(Response $response): ?string
    {
        $code = $response->json('code');
        if (! is_string($code)) {
            return null;
        }

        return strtoupper(str_replace(' ', '_', $code));
    }

    private static function reasonCode(Response $response): string
    {
        $reason = $response->json('reason');
        $code = is_array($reason) ? ($reason['code'] ?? null) : $reason;

        return is_string($code) && $code !== '' ? strtoupper($code) : 'PAYMENT_FAILED';
    }

    private static function reasonLabel(string $code): string
    {
        return match ($code) {
            'PAYER_NOT_FOUND', 'PAYEE_NOT_FOUND' => 'Numéro sans compte Mobile Money actif.',
            'COULD_NOT_PERFORM_TRANSACTION', 'EXPIRED' => 'Paiement non validé dans le délai (5 minutes).',
            'APPROVAL_REJECTED', 'PAYMENT_NOT_APPROVED' => 'Paiement refusé par le titulaire du compte.',
            'TRANSACTION_CANCELED' => 'Opération annulée.',
            'NOT_ENOUGH_FUNDS' => 'Fonds insuffisants.',
            'PAYER_LIMIT_REACHED' => 'Plafond Mobile Money atteint.',
            'PAYEE_NOT_ALLOWED_TO_RECEIVE' => 'Ce compte ne peut pas recevoir de fonds.',
            'INTERNAL_PROCESSING_ERROR' => 'Opération refusée par MTN MoMo (souvent : fonds insuffisants).',
            default => 'Opération refusée par MTN MoMo.',
        };
    }
}
