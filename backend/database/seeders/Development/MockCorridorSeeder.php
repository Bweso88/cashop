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
 * Corridors de TEST au départ du Congo-Brazzaville, pour les connecteurs simulés.
 * Ils ne décrivent PAS la couverture réelle des prestataires (NOT_CONFIRMED) : la liste
 * officielle des pays et modes de réception sera fournie par chaque réseau.
 */
class MockCorridorSeeder extends Seeder
{
    public const ORIGIN = 'CG';

    /** Réseau => [pays de destination => [devise, modes de réception]] */
    public const CORRIDORS = [
        'western_union' => [
            'FR' => ['EUR', [PayoutMethod::CashPickup]],
            'BE' => ['EUR', [PayoutMethod::CashPickup]],
            'CM' => ['XAF', [PayoutMethod::CashPickup, PayoutMethod::MobileMoney]],
            'CI' => ['XOF', [PayoutMethod::CashPickup]],
            'US' => ['USD', [PayoutMethod::CashPickup]],
        ],
        'moneygram' => [
            'FR' => ['EUR', [PayoutMethod::CashPickup]],
            'CM' => ['XAF', [PayoutMethod::CashPickup]],
            'SN' => ['XOF', [PayoutMethod::CashPickup, PayoutMethod::MobileMoney]],
            'GB' => ['GBP', [PayoutMethod::CashPickup]],
        ],
        'ria' => [
            'FR' => ['EUR', [PayoutMethod::CashPickup]],
            'CI' => ['XOF', [PayoutMethod::CashPickup, PayoutMethod::MobileMoney]],
            'CM' => ['XAF', [PayoutMethod::CashPickup]],
            'US' => ['USD', [PayoutMethod::CashPickup]],
        ],
    ];

    public function run(): void
    {
        $now = now();
        $providers = DB::table('providers')->pluck('id', 'code');

        // Simulation activée pour les portefeuilles et les réseaux ; Visa reste désactivé (phase ultérieure).
        DB::table('providers')->whereIn('code', ['mtn_momo', 'airtel_money', 'western_union', 'moneygram', 'ria'])
            ->update(['mode' => ProviderMode::Mock->value, 'is_enabled' => true]);
        DB::table('countries')->where('code', self::ORIGIN)->update(['is_send_enabled' => true, 'is_receive_enabled' => true]);

        $config = fn (string $provider, string $country, string $currency, ProviderDirection $direction) => DB::table('provider_country_configs')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'provider_id' => $providers[$provider],
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

        // Financement (collecte) et versement Mobile Money au Congo.
        foreach (['mtn_momo', 'airtel_money'] as $wallet) {
            $config($wallet, self::ORIGIN, 'XAF', ProviderDirection::Both);
            DB::table('country_payment_methods')->insertOrIgnore([
                'id' => (string) Str::uuid7(), 'country_code' => self::ORIGIN, 'method' => PaymentMethod::MobileMoney->value,
                'provider_id' => $providers[$wallet], 'currency_code' => 'XAF', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (self::CORRIDORS as $network => $destinations) {
            $config($network, self::ORIGIN, 'XAF', ProviderDirection::Send);
            foreach ($destinations as $country => [$currency, $methods]) {
                DB::table('countries')->where('code', $country)->update(['is_receive_enabled' => true]);
                $config($network, $country, $currency, ProviderDirection::Receive);
                foreach ($methods as $method) {
                    DB::table('country_payout_methods')->insertOrIgnore([
                        'id' => (string) Str::uuid7(), 'country_code' => $country, 'method' => $method->value,
                        'provider_id' => $providers[$network], 'currency_code' => $currency, 'is_enabled' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
