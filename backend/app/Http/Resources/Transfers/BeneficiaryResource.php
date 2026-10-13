<?php

namespace App\Http\Resources\Transfers;

use App\Domain\Payments\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Beneficiary */
class BeneficiaryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'country' => $this->country_code,
            'phone' => $this->phone_e164,
            'city' => $this->city,
            'relationship' => $this->relationship,
            'details' => $this->details ?? (object) [],
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
