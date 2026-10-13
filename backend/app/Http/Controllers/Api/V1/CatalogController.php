<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Services\QuoteService;
use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Enums\ProviderKind;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Enums\SimulationScenario;
use App\Domain\Providers\Models\Provider;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Ce que l'administrateur a activé : moyens de financement, réseaux, corridors, champs requis. */
class CatalogController extends Controller
{
    private const PAYOUT_LABELS = [
        'cash_pickup' => 'Retrait en espèces en agence',
        'mobile_money' => 'Réception sur portefeuille mobile',
        'bank_account' => 'Compte bancaire',
        'card' => 'Carte bancaire',
    ];

    public function fundingProviders(): JsonResponse
    {
        $providers = Provider::query()->where('kind', ProviderKind::Wallet)->where('is_enabled', true)
            ->whereHas('countryConfigs', fn ($q) => $q->where('country_code', QuoteService::ORIGIN_COUNTRY)->where('is_enabled', true))
            ->orderBy('name')->get();

        return response()->json(['data' => $providers->map(fn (Provider $p) => $this->provider($p))]);
    }

    public function networks(): JsonResponse
    {
        $networks = Provider::query()->where('kind', ProviderKind::Remittance)->where('is_enabled', true)->orderBy('name')->get();

        return response()->json(['data' => $networks->map(function (Provider $p) {
            $corridors = DB::table('country_payout_methods as m')->join('countries as c', 'c.code', '=', 'm.country_code')
                ->where('m.provider_id', $p->id)->where('m.is_enabled', true)
                ->orderBy('c.name')->get(['m.country_code', 'c.name', 'm.currency_code', 'm.method']);

            return [
                ...$this->provider($p),
                'destinations' => $corridors->groupBy('country_code')->map(fn ($rows) => [
                    'country' => $rows[0]->country_code,
                    'name' => $rows[0]->name,
                    'currency' => $rows[0]->currency_code,
                    'payout_methods' => $rows->map(fn ($r) => ['code' => $r->method, 'label' => self::PAYOUT_LABELS[$r->method] ?? $r->method])->values(),
                ])->values(),
            ];
        })]);
    }

    /** Champs bénéficiaire demandés par le réseau pour un corridor (configurables par connecteur). */
    public function fields(Request $request, string $network, ConnectorRegistry $registry): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'payout_method' => ['required', Rule::enum(PayoutMethod::class)],
        ]);
        $provider = Provider::query()->where('code', $network)->where('kind', ProviderKind::Remittance)->where('is_enabled', true)->first()
            ?? throw new ApiException('NOT_FOUND', 'Réseau introuvable.', 404);

        try {
            $fields = $registry->remittance($provider->code, $provider->mode)->requiredFields($data['country'], PayoutMethod::from($data['payout_method']));
        } catch (ConnectorUnavailableException $e) {
            throw new ApiException('PROVIDER_UNAVAILABLE', $e->getMessage(), 503);
        }

        return response()->json(['data' => array_map(fn ($f) => $f->toArray(), $fields)]);
    }

    /** Scénarios de démonstration : uniquement si tous les prestataires actifs sont en simulation. */
    public function scenarios(): JsonResponse
    {
        $simulationOnly = ! Provider::query()->where('is_enabled', true)->where('mode', '!=', ProviderMode::Mock->value)->exists();

        return response()->json([
            'enabled' => $simulationOnly,
            'data' => $simulationOnly ? array_map(fn (SimulationScenario $s) => ['code' => $s->value, 'label' => $s->label()], SimulationScenario::cases()) : [],
        ]);
    }

    private function provider(Provider $p): array
    {
        return [
            'code' => $p->code,
            'name' => $p->name,
            'environment' => $p->mode->value,
            'is_simulation' => $p->mode === ProviderMode::Mock,
        ];
    }
}
