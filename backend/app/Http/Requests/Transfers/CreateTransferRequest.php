<?php

namespace App\Http\Requests\Transfers;

use App\Domain\Payments\Models\TransferQuote;
use App\Domain\Providers\Enums\ProviderKind;
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
            // Inutile pour une carte : elle est saisie sur la page sécurisée du prestataire.
            'funding_msisdn' => $this->paysByCard()
                ? ['prohibited']
                : ['required', 'regex:'.($this->quote()?->environment === ProviderMode::Sandbox ? self::SANDBOX_MSISDN : self::CONGO_MSISDN)],
            // Cashop ne collecte jamais de données de carte (PCI DSS) : refus explicite.
            'card_number' => ['prohibited'],
            'pan' => ['prohibited'],
            'cvv' => ['prohibited'],
            'cvc' => ['prohibited'],
            'expiry' => ['prohibited'],
            // PIN de transaction Cashop (jamais le PIN Mobile Money).
            'pin' => ['required', 'digits:6'],
            'purpose' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:140'],
            'simulation_scenario' => ['nullable', Rule::enum(SimulationScenario::class)],
        ];
    }

    private ?TransferQuote $quote = null;

    private function quote(): ?TransferQuote
    {
        if ($this->quote === null && is_string($this->input('quote_id')) && Str::isUuid($this->input('quote_id'))) {
            $this->quote = TransferQuote::query()->with('fundingProvider')->whereKey($this->input('quote_id'))->where('user_id', $this->user()?->id)->first();
        }

        return $this->quote;
    }

    private function paysByCard(): bool
    {
        return $this->quote()?->fundingProvider?->kind === ProviderKind::Card;
    }

    public function messages(): array
    {
        $card = 'Cashop ne collecte jamais vos données de carte : elles se saisissent sur la page sécurisée du prestataire.';

        return [
            'funding_msisdn.regex' => 'Numéro Mobile Money congolais attendu (+242 suivi de 9 chiffres).',
            'funding_msisdn.prohibited' => 'Aucun numéro Mobile Money n’est utilisé pour un paiement par carte.',
            'card_number.prohibited' => $card, 'pan.prohibited' => $card, 'cvv.prohibited' => $card, 'cvc.prohibited' => $card, 'expiry.prohibited' => $card,
        ];
    }
}
