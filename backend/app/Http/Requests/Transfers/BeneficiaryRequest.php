<?php

namespace App\Http\Requests\Transfers;

use App\Http\Requests\ApiRequest;

class BeneficiaryRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'phone' => ['nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'city' => ['nullable', 'string', 'max:80'],
            'relationship' => ['nullable', 'string', 'max:40'],
            // Champs complémentaires exigés par un réseau (structure configurable).
            'details' => ['nullable', 'array', 'max:20'],
            'details.*' => ['nullable', 'string', 'max:200'],
        ];
    }
}
