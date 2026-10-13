<?php

namespace App\Http\Resources\Transfers;

use App\Domain\Payments\Models\TransferQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransferQuote */
class QuoteResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network' => ['code' => $this->provider->code, 'name' => $this->provider->name],
            'funding_provider' => ['code' => $this->fundingProvider->code, 'name' => $this->fundingProvider->name],
            'environment' => $this->environment->value,
            'is_simulated' => $this->is_simulated,
            'simulation_notice' => $this->is_simulated ? 'Devis simulé : frais et taux fictifs.' : null,
            'send' => ['amount' => $this->send_amount, 'currency' => $this->send_currency],
            'fees' => $this->fee_breakdown,
            'total_fees' => ['amount' => $this->total_fees, 'currency' => $this->send_currency],
            'total_debit' => ['amount' => $this->total_debit, 'currency' => $this->send_currency],
            'receive' => ['amount' => $this->receive_amount, 'currency' => $this->receive_currency],
            'fx_rate' => (string) $this->fx_rate,
            'fx_rate_source' => $this->fx_rate_source,
            'destination_country' => $this->destination_country,
            'payout_method' => $this->payout_method->value,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
