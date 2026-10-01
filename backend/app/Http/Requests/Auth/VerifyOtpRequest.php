<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class VerifyOtpRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
