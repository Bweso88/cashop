<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class TotpCodeRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['code' => ['required', 'digits:6']];
    }
}
