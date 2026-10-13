<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PayoutMethod;
use Illuminate\Support\Facades\DB;

/**
 * Frais de service Cashop (table fees), en plus des frais du réseau.
 * Une règle s'applique si chacun de ses critères renseignés correspond (critère NULL = tous).
 */
class FeeEngine
{
    /** @return list<array{component: string, label: string, amount: int}> */
    public function serviceFees(int $sendAmount, string $currency, string $sourceCountry, string $destinationCountry, string $providerId, PayoutMethod $method): array
    {
        $rules = DB::table('fees')
            ->where('is_active', true)
            ->where('currency_code', $currency)
            ->where('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->where(fn ($q) => $q->whereNull('provider_id')->orWhere('provider_id', $providerId))
            ->where(fn ($q) => $q->whereNull('source_country')->orWhere('source_country', $sourceCountry))
            ->where(fn ($q) => $q->whereNull('destination_country')->orWhere('destination_country', $destinationCountry))
            ->where(fn ($q) => $q->whereNull('payout_method')->orWhere('payout_method', $method->value))
            ->where(fn ($q) => $q->whereNull('min_amount')->orWhere('min_amount', '<=', $sendAmount))
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $sendAmount))
            ->orderByDesc('priority')
            ->get();

        return $rules->map(fn ($rule) => [
            'component' => $rule->component,
            'label' => $rule->name,
            // Pourcentage arrondi à l'unité supérieure, en entiers.
            'amount' => (int) $rule->fixed_amount + intdiv($sendAmount * (int) $rule->percentage_bps + 9_999, 10_000),
        ])->filter(fn ($fee) => $fee['amount'] > 0)->values()->all();
    }
}
