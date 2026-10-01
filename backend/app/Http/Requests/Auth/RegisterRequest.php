<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'password' => ['required', 'string', Password::defaults()],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2', 'exists:countries,code'],
            ...$this->deviceRules(),
        ];
    }
}
