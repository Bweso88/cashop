<?php

namespace App\Http\Requests\Transfers;

use App\Domain\Payments\Models\TransferQuote;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Enums\SimulationScenario;
use App\Http\Requests\ApiRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateTransferRequest extends ApiRequest
{
    public const CONGO_MSISDN = '/^\+242[0-9]{9}$/';

    /** Numéros de test MTN (ex. +46733123450) : forme internationale générique. */
    public const SANDBOX_MSISDN = '/^\+[1-9][0-9]{7,14}$/';

    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'uuid'],
            'beneficiary_id' => ['required', 'uuid'],
            // Numéro Mobile Money à débiter : +242 au Congo ; numéros de test de l'opérateur en sandbox.
            'funding_msisdn' => ['required', 'regex:'.($this->quoteEnvironment() === ProviderMode::Sandbox ? self::SANDBOX_MSISDN : self::CONGO_MSISDN)],
            // PIN de transaction Cashop (jamais le PIN Mobile Money).
            'pin' => ['required', 'digits:6'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:140'],
            'simulation_scenario' => ['nullable', Rule::enum(SimulationScenario::class)],
        ];
    }

    private function quoteEnvironment(): ?ProviderMode
    {
        $quote = is_string($this->input('quote_id')) && Str::isUuid($this->input('quote_id'))
            ? TransferQuote::query()->whereKey($this->input('quote_id'))->where('user_id', $this->user()?->id)->first()
            : null;

        return $quote?->environment;
    }

    public function messages(): array
    {
        return ['funding_msisdn.regex' => 'Numéro Mobile Money congolais attendu (+242 suivi de 9 chiffres).'];
    }
}
