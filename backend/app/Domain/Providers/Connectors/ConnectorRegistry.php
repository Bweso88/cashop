<?php

namespace App\Domain\Providers\Connectors;

use App\Domain\Providers\Connectors\Contracts\RemittanceConnector;
use App\Domain\Providers\Connectors\Contracts\WalletConnector;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\Simulated\SimulatedRemittanceConnector;
use App\Domain\Providers\Connectors\Simulated\SimulatedWalletConnector;
use App\Domain\Providers\Connectors\Simulated\SimulationStore;
use App\Domain\Providers\Enums\ProviderKind;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;

/**
 * Fournit le connecteur d'un prestataire pour un environnement donné.
 *
 * Règles d'isolation :
 *  - « mock » : connecteur simulé, aucun mouvement de fonds réel.
 *  - « sandbox » / « production » : connecteur officiel uniquement (aucun n'existe tant que la
 *    documentation et les accès partenaires n'ont pas été obtenus).
 *  - « production » exige en plus CASHOP_PRODUCTION_ENABLED=true.
 *  - Une opération créée dans un environnement n'est jamais traitée dans un autre : pas de
 *    bascule automatique de la simulation vers le réel.
 */
class ConnectorRegistry
{
    public function __construct(private readonly SimulationStore $store) {}

    public function wallet(string $code, ProviderMode $environment): WalletConnector
    {
        return $this->resolve($code, $environment, ProviderKind::Wallet);
    }

    public function remittance(string $code, ProviderMode $environment): RemittanceConnector
    {
        return $this->resolve($code, $environment, ProviderKind::Remittance);
    }

    private function resolve(string $code, ProviderMode $environment, ProviderKind $kind): WalletConnector|RemittanceConnector
    {
        $provider = Provider::byCode($code);
        if ($provider->kind !== $kind) {
            throw new ConnectorUnavailableException("{$provider->name} n'est pas un prestataire de type {$kind->value}.");
        }
        if ($provider->mode !== $environment) {
            throw new ConnectorUnavailableException(
                "{$provider->name} est configuré en {$provider->mode->value} : une opération {$environment->value} ne peut pas y être traitée (aucune bascule automatique)."
            );
        }

        if ($environment === ProviderMode::Mock) {
            $delay = (int) config('cashop.simulation.delay_seconds');
            $secret = (string) config('cashop.simulation.webhook_secret');

            return $kind === ProviderKind::Wallet
                ? new SimulatedWalletConnector($code, $this->store, $delay, $secret)
                : new SimulatedRemittanceConnector($code, $this->store, $delay, $secret);
        }

        if ($environment === ProviderMode::Production && ! config('cashop.environments.production_enabled')) {
            throw new ConnectorUnavailableException('Environnement de production désactivé (CASHOP_PRODUCTION_ENABLED=false).');
        }

        $class = config("cashop.connectors.official.{$code}");
        if (! $class) {
            throw new ConnectorUnavailableException(
                "Connecteur officiel {$provider->name} non disponible : documentation, contrat et identifiants partenaire requis (voir docs/PROVIDERS.md)."
            );
        }

        return app($class, ['environment' => $environment]);
    }
}
