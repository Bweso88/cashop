<?php

namespace App\Domain\Providers\Connectors\Simulated;

use Illuminate\Support\Facades\Cache;

/**
 * Mémoire des connecteurs simulés : ce que « le prestataire » a enregistré pour chaque référence.
 * Stockée dans le cache applicatif, totalement séparée des données Cashop.
 */
class SimulationStore
{
    public function get(string $provider, string $reference): ?array
    {
        return Cache::get($this->key($provider, $reference));
    }

    public function put(string $provider, string $reference, array $record): void
    {
        Cache::put($this->key($provider, $reference), $record, now()->addDays(30));
    }

    private function key(string $provider, string $reference): string
    {
        return "simulation:{$provider}:{$reference}";
    }
}
