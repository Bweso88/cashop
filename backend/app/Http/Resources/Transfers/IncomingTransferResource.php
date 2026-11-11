<?php

namespace App\Http\Resources\Transfers;

use App\Domain\Payments\Models\IncomingTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IncomingTransfer */
class IncomingTransferResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $msisdn = $this->payout_msisdn;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_terminal' => $this->status->isTerminal(),
            'environment' => $this->environment->value,
            'is_simulation' => $this->isSimulated(),
            'simulation_notice' => $this->isSimulated() ? 'SIMULATION — aucun fonds réel versé. Les références « SIM- » ne sont pas reconnues par les réseaux de transfert.' : null,
            'network' => ['code' => $this->provider->code, 'name' => $this->provider->name],
            'network_reference' => $this->network_reference,
            'sender' => ['name' => $this->sender_name, 'country' => $this->sender_country],
            'amount' => ['amount' => $this->amount, 'currency' => $this->currency],
            'payout' => [
                'provider' => ['code' => $this->payoutProvider->code, 'name' => $this->payoutProvider->name],
                'msisdn_masked' => substr($msisdn, 0, 4).' ••• '.substr($msisdn, -3),
                'reference' => $this->payout_reference,
            ],
            'failure' => $this->failure_code ? ['code' => $this->failure_code, 'message' => $this->failure_reason] : null,
            'paid_out_at' => $this->paid_out_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
