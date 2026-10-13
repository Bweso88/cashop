<?php

namespace App\Domain\Providers\Connectors\Contracts;

use App\Domain\Providers\Connectors\DTO\WebhookNotification;
use App\Domain\Providers\Connectors\Exceptions\InvalidWebhookException;
use App\Domain\Providers\Enums\ConnectorCapability;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Http\Request;

interface Connector
{
    public function providerCode(): string;

    public function environment(): ProviderMode;

    /** @return list<ConnectorCapability> */
    public function capabilities(): array;

    public function supports(ConnectorCapability $capability): bool;

    /** Disponibilité du service (true = joignable). */
    public function health(): bool;

    /**
     * Authentifie une notification selon le mécanisme officiel du prestataire.
     *
     * @throws InvalidWebhookException
     */
    public function verifyWebhook(Request $request): WebhookNotification;
}
