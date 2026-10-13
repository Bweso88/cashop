<?php

namespace App\Http\Requests\Transfers;

use App\Domain\Providers\Enums\SimulationScenario;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class CreateTransferRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'beneficiary_id' => ['required', 'uuid'],
            // Numéro Mobile Money à débiter (Congo : +242).
            'funding_msisdn' => ['required', 'regex:/^\+242[0-9]{9}$/'],
            // PIN de transaction Cashop (jamais le PIN Mobile Money).
            'pin' => ['required', 'digits:6'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:140'],
            'simulation_scenario' => ['nullable', Rule::enum(SimulationScenario::class)],
        ];
    }

    public function messages(): array
    {
        return ['funding_msisdn.regex' => 'Numéro Mobile Money congolais attendu (+242 suivi de 9 chiffres).'];
    }
}
