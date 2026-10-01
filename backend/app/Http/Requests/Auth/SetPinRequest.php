<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class SetPinRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6'],
            'current_pin' => ['nullable', 'digits:6'],
        ];
    }
}
