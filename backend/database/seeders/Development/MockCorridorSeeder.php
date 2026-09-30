<?php

namespace Database\Seeders\Development;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PayoutMethod;
use App\Domain\Providers\Enums\ConfirmationStatus;
use App\Domain\Providers\Enums\ProviderDirection;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Corridors de TEST pour les Mock providers. Ils ne décrivent PAS la couverture réelle des
 * providers (NOT_CONFIRMED) : ce sont des fixtures permettant d'exercer tous les parcours.
 */
class MockCorridorSeeder extends Seeder
{
    /** provider => [[pays, devise, direction, modes de réception]] */
    private const CORRIDORS = [
        'mtn_momo' => [['CM', 'XAF', ProviderDirection::Both, [PayoutMethod::MobileMoney]]],
        'airtel_money' => [['CG', 'XAF', ProviderDirection::Both, [PayoutMethod::MobileMoney]]],
        'visa_direct' => [
            ['FR', 'EUR', ProviderDirection::Both, [PayoutMethod::Card]],
            ['GB', 'GBP', ProviderDirection::Both, [PayoutMethod::Card]],
            ['US', 'USD', ProviderDirection::Both, [PayoutMethod::Card]],
        ],
        'moneygram' => [
            ['CM', 'XAF', ProviderDirection::Receive, [PayoutMethod::CashPickup]],
            ['GA', 'XAF', ProviderDirection::Receive, [PayoutMethod::CashPickup]],
        ],
        'western_union' => [
            ['CM', 'XAF', ProviderDirection::Receive, [PayoutMethod::CashPickup]],
            ['TD', 'XAF', ProviderDirection::Receive, [PayoutMethod::CashPickup]],
        ],
    ];

    public function run(): void
    {
        $now = now();
        $providers = DB::table('providers')->pluck('id', 'code');

        DB::table('providers')->update(['mode' => ProviderMode::Mock->value, 'is_enabled' => true]);
        DB::table('countries')->update(['is_send_enabled' => true, 'is_receive_enabled' => true]);

        foreach (self::CORRIDORS as $code => $corridors) {
            foreach ($corridors as [$country, $currency, $direction, $payoutMethods]) {
                DB::table('provider_country_configs')->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'provider_id' => $providers[$code],
                    'country_code' => $country,
                    'currency_code' => $currency,
                    'direction' => $direction->value,
                    'mode' => ProviderMode::Mock->value,
                    'is_enabled' => true,
                    'settings' => json_encode(['fixture' => true]),
                    'confirmation_status' => ConfirmationStatus::NotConfirmed->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach ($payoutMethods as $method) {
                    DB::table('country_payout_methods')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'country_code' => $country,
                        'method' => $method->value,
                        'provider_id' => $providers[$code],
                        'currency_code' => $currency,
                        'is_enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Paiement depuis le wallet Cashop, dans les pays d'envoi de test.
        foreach ([['FR', 'EUR'], ['BE', 'EUR'], ['GB', 'GBP'], ['US', 'USD'], ['CM', 'XAF']] as [$country, $currency]) {
            DB::table('country_payment_methods')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'country_code' => $country,
                'method' => PaymentMethod::Wallet->value,
                'provider_id' => null,
                'currency_code' => $currency,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
