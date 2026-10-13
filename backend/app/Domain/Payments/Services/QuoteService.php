<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Payments\Enums\QuoteStatus;
use App\Domain\Payments\Models\TransferQuote;
use App\Domain\Providers\Connectors\ConnectorRegistry;
use App\Domain\Providers\Connectors\DTO\RemittanceQuoteRequest;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Enums\ProviderKind;
use App\Domain\Providers\Enums\ProviderMode;
use App\Domain\Providers\Models\Provider;
use App\Models\User;
use App\Support\Http\ApiException;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Devis : frais du réseau + frais de service Cashop + taux de change, figés jusqu'à expiration.
 * Le client paie en XAF depuis le Congo ; le bénéficiaire reçoit dans la devise du corridor.
 */
class QuoteService
{
    public const ORIGIN_COUNTRY = 'CG';

    public const SEND_CURRENCY = 'XAF';

    public function __construct(
        private readonly ConnectorRegistry $connectors,
        private readonly FxService $fx,
        private readonly FeeEngine $fees,
        private readonly LimitChecker $limits,
    ) {}

    public function create(User $user, string $networkCode, string $fundingCode, string $destinationCountry, PayoutMethod $payoutMethod, int $sendAmount): TransferQuote
    {
        $network = $this->enabledProvider($networkCode, ProviderKind::Remittance);
        $funding = $this->enabledProvider($fundingCode, ProviderKind::Wallet);
        if ($network->mode !== $funding->mode) {
            throw new ApiException('ENVIRONMENT_MISMATCH', 'Le portefeuille et le réseau ne sont pas configurés dans le même environnement.', 422);
        }
        $environment = $network->mode;

        $corridor = DB::table('country_payout_methods')
            ->where('provider_id', $network->id)->where('country_code', $destinationCountry)
            ->where('method', $payoutMethod->value)->where('is_enabled', true)->first();
        if (! $corridor) {
            throw new ApiException('CORRIDOR_UNAVAILABLE', "{$network->name} ne propose pas ce mode de réception vers ce pays.", 422);
        }

        $this->limits->assertAllowed($user, $sendAmount, self::SEND_CURRENCY);

        try {
            $providerQuote = $this->connectors->remittance($network->code, $environment)->quote(new RemittanceQuoteRequest(
                originCountry: self::ORIGIN_COUNTRY,
                destinationCountry: $destinationCountry,
                payoutMethod: $payoutMethod,
                sendAmount: $sendAmount,
                sendCurrency: self::SEND_CURRENCY,
                receiveCurrency: $corridor->currency_code,
            ));
        } catch (ConnectorUnavailableException $e) {
            throw new ApiException('PROVIDER_UNAVAILABLE', $e->getMessage(), 503);
        } catch (ConnectorException $e) {
            throw new ApiException('QUOTE_UNAVAILABLE', "Devis indisponible auprès de {$network->name} : {$e->getMessage()}", 503);
        }

        if ($providerQuote->fxRate !== null) {
            $rate = BigDecimal::of($providerQuote->fxRate);
            $rateSource = strtoupper($network->code);
        } else {
            $fx = $this->fx->rate(self::SEND_CURRENCY, $corridor->currency_code);
            [$rate, $rateSource] = [$fx['rate'], $fx['source']];
        }

        $simulated = $providerQuote->isSimulated || $environment === ProviderMode::Mock;
        $breakdown = [[
            'component' => 'PROVIDER',
            'label' => "Frais {$network->name}".($simulated ? ' (fictifs — simulation)' : ''),
            'amount' => $providerQuote->providerFee,
        ], ...$this->fees->serviceFees($sendAmount, self::SEND_CURRENCY, self::ORIGIN_COUNTRY, $destinationCountry, $network->id, $payoutMethod)];
        $totalFees = array_sum(array_column($breakdown, 'amount'));

        $ttl = now()->addMinutes(config('cashop.transfers.quote_ttl_minutes'));

        return TransferQuote::create([
            'user_id' => $user->id,
            'provider_id' => $network->id,
            'funding_provider_id' => $funding->id,
            'environment' => $environment,
            'send_amount' => $sendAmount,
            'send_currency' => self::SEND_CURRENCY,
            'receive_amount' => $this->fx->convert($sendAmount, self::SEND_CURRENCY, $corridor->currency_code, $rate),
            'receive_currency' => $corridor->currency_code,
            'fx_rate' => (string) $rate,
            'fx_rate_source' => $rateSource,
            'fee_breakdown' => $breakdown,
            'total_fees' => $totalFees,
            'total_debit' => $sendAmount + $totalFees,
            'payment_method' => PaymentMethod::MobileMoney,
            'payout_method' => $payoutMethod,
            'destination_country' => $destinationCountry,
            'provider_quote_reference' => $providerQuote->providerQuoteReference,
            'is_simulated' => $simulated,
            'status' => QuoteStatus::Active,
            'expires_at' => $providerQuote->expiresAt < $ttl ? $providerQuote->expiresAt : $ttl,
        ]);
    }

    private function enabledProvider(string $code, ProviderKind $kind): Provider
    {
        $provider = Provider::query()->where('code', $code)->where('kind', $kind->value)->where('is_enabled', true)->first();
        if (! $provider) {
            throw new ApiException('PROVIDER_UNAVAILABLE', 'Ce prestataire n’est pas disponible.', 422);
        }

        return $provider;
    }
}
