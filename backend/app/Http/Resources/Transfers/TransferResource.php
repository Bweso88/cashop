<?php

namespace App\Http\Resources\Transfers;

use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transfer */
class TransferResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $collection = $this->relationLoaded('paymentAttempts')
            ? $this->paymentAttempts->firstWhere('direction.value', 'COLLECTION')
            : null;
        $msisdn = $this->funding_account;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_terminal' => $this->status->isTerminal(),
            'is_delivered' => $this->status->isDelivered(),
            'environment' => $this->environment->value,
            'is_simulation' => $this->isSimulated(),
            'simulation_notice' => $this->isSimulated() ? ReceiptService::SIMULATION_NOTICE : null,
            'simulation_scenario' => $this->simulation_scenario,
            'network' => ['code' => $this->provider->code, 'name' => $this->provider->name],
            'funding' => [
                'provider' => ['code' => $this->fundingProvider->code, 'name' => $this->fundingProvider->name],
                'msisdn_masked' => substr($msisdn, 0, 4).' ••• '.substr($msisdn, -3),
                'payment_status' => $collection?->status->value,
                'payment_reference' => $collection?->provider_reference,
            ],
            'beneficiary' => [
                'id' => $this->beneficiary->id,
                'name' => $this->beneficiary->fullName(),
                'country' => $this->beneficiary->country_code,
            ],
            'send' => ['amount' => $this->send_amount, 'currency' => $this->send_currency],
            'total_fees' => ['amount' => $this->total_fees, 'currency' => $this->send_currency],
            'total_debit' => ['amount' => $this->total_debit, 'currency' => $this->send_currency],
            'receive' => ['amount' => $this->receive_amount, 'currency' => $this->receive_currency],
            'fx_rate' => (string) $this->fx_rate,
            'payout_method' => $this->payout_method->value,
            'destination_country' => $this->destination_country,
            'external_reference' => $this->external_reference,
            'provider_reference' => $this->provider_reference,
            'pickup_code' => $this->pickup_code,
            'failure' => $this->failure_code ? ['code' => $this->failure_code, 'message' => $this->failure_reason] : null,
            'refund' => $this->whenLoaded('refunds', fn () => ($refund = $this->refunds->sortByDesc('created_at')->first()) ? [
                'status' => $refund->status->value, 'amount' => $refund->amount, 'currency' => $refund->currency_code, 'reference' => $refund->provider_reference,
            ] : null),
            'receipts' => $this->whenLoaded('receipts', fn () => $this->receipts->map(fn ($r) => ['kind' => $r->kind, 'number' => $r->number])->values()),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'from' => $e->from_status?->value,
                'to' => $e->to_status->value,
                'label' => $e->to_status->label(),
                'source' => $e->source->value,
                'at' => $e->created_at->toIso8601String(),
            ])->values()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
