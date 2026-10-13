<?php

namespace App\Domain\Providers\Connectors\Simulated;

use App\Domain\Providers\Connectors\DTO\WebhookNotification;
use App\Domain\Providers\Connectors\Exceptions\InvalidWebhookException;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Base des connecteurs simulés. Toute référence produite commence par « SIM- » et ne peut pas
 * être confondue avec une référence réelle d'un prestataire.
 */
abstract class SimulatedConnector
{
    public const SIGNATURE_HEADER = 'X-Cashop-Simulator-Signature';

    public const TIMESTAMP_HEADER = 'X-Cashop-Simulator-Timestamp';

    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function __construct(
        protected readonly string $code,
        protected readonly SimulationStore $store,
        protected readonly int $delaySeconds,
        private readonly string $webhookSecret,
    ) {}

    public function providerCode(): string
    {
        return $this->code;
    }

    public function environment(): ProviderMode
    {
        return ProviderMode::Mock;
    }

    public function supports(ConnectorCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function health(): bool
    {
        return true;
    }

    /** Signature HMAC-SHA256 des notifications simulées (horodatage inclus contre le rejeu). */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public function verifyWebhook(Request $request): WebhookNotification
    {
        $timestamp = (int) $request->header(self::TIMESTAMP_HEADER);
        $signature = (string) $request->header(self::SIGNATURE_HEADER);
        if ($timestamp === 0 || $signature === '') {
            throw new InvalidWebhookException('Signature absente.');
        }
        if (abs(now()->getTimestamp() - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            throw new InvalidWebhookException('Horodatage hors de la fenêtre autorisée.');
        }
        if (! hash_equals(self::sign($this->webhookSecret, $timestamp, $request->getContent()), $signature)) {
            throw new InvalidWebhookException('Signature invalide.');
        }

        return new WebhookNotification(
            eventId: $request->json('event_id'),
            reference: $request->json('reference'),
            occurredAt: Carbon::createFromTimestamp($timestamp),
        );
    }

    protected function elapsed(array $record): int
    {
        return now()->getTimestamp() - $record['created_at'];
    }

    protected function simulatedReference(string $prefix): string
    {
        return 'SIM-'.$prefix.'-'.Str::upper(Str::random(8));
    }
}
