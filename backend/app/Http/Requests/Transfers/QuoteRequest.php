<?php

namespace App\Http\Requests\Transfers;

use App\Domain\Payments\Enums\PayoutMethod;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'network' => ['required', 'string', 'exists:providers,code'],
            'funding_provider' => ['required', 'string', 'exists:providers,code'],
            'destination_country' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'payout_method' => ['required', Rule::enum(PayoutMethod::class)],
            // Montant envoyé, en unités mineures XAF (le XAF n'a pas de décimale).
            'send_amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
        ];
    }
}
