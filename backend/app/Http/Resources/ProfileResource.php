<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ProfileResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $kyc = $this->kycProfile;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone_e164,
            'first_name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'country' => $profile?->country_of_residence,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'kyc_level' => $kyc?->level_code,
            'kyc_status' => $kyc?->status?->value,
            'mfa_enabled' => $this->hasMfaEnabled(),
            'pin_set' => $this->hasPin(),
        ];
    }
}
