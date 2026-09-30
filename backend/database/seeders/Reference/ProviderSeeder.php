<?php

namespace Database\Seeders\Reference;

use App\Domain\Providers\Enums\ConfirmationStatus;
use App\Domain\Providers\Enums\ProviderCapability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Providers et matrice de capacités, alignée sur docs/PROVIDERS.md.
 *
 * Une capacité n'est CONFIRMED que si une page de documentation officielle la décrit
 * (lien dans source_reference). Tout le reste est NOT_CONFIRMED et is_supported = false :
 * les adapters réels ne l'utiliseront pas tant que la phase 14 ne l'aura pas confirmée.
 */
class ProviderSeeder extends Seeder
{
    private const MONEYGRAM_TRANSFER_API = 'https://developer.moneygram.com/moneygram-developer/docs/transfer-api';

    private const MONEYGRAM_UPDATE = 'https://developer.moneygram.com/moneygram-developer/docs/update-a-transaction';

    public const PROVIDERS = [
        'visa_direct' => ['name' => 'Visa Direct', 'class' => 'VisaDirect\\VisaDirectProvider', 'mock' => 'Mock\\MockVisaProvider'],
        'moneygram' => ['name' => 'MoneyGram', 'class' => 'MoneyGram\\MoneyGramProvider', 'mock' => 'Mock\\MockMoneyGramProvider'],
        'western_union' => ['name' => 'Western Union', 'class' => 'WesternUnion\\WesternUnionProvider', 'mock' => 'Mock\\MockWesternUnionProvider'],
        'mtn_momo' => ['name' => 'MTN Mobile Money', 'class' => 'MtnMoMo\\MtnMoMoProvider', 'mock' => 'Mock\\MockMtnProvider'],
        'airtel_money' => ['name' => 'Airtel Money', 'class' => 'AirtelMoney\\AirtelMoneyProvider', 'mock' => 'Mock\\MockAirtelProvider'],
    ];

    /**
     * Capacités confirmées par la documentation officielle.
     *
     * @return array<string, array<string, string>> provider => [capability => source]
     */
    private function confirmed(): array
    {
        return [
            'moneygram' => [
                ProviderCapability::Quote->value => self::MONEYGRAM_TRANSFER_API,
                ProviderCapability::Create->value => self::MONEYGRAM_UPDATE,
                ProviderCapability::Commit->value => self::MONEYGRAM_TRANSFER_API,
            ],
        ];
    }

    /**
     * Indications issues de sources publiques non officielles : restent NOT_CONFIRMED.
     *
     * @return array<string, array<string, string>>
     */
    private function secondary(): array
    {
        $note = 'PUBLIC-SECONDAIRE : à confirmer sur le portail officiel';

        return [
            'visa_direct' => [ProviderCapability::Create->value => $note],
            'mtn_momo' => [
                ProviderCapability::Collection->value => $note,
                ProviderCapability::Disbursement->value => $note,
                ProviderCapability::Remittance->value => $note,
                ProviderCapability::Status->value => $note,
                ProviderCapability::Webhook->value => $note,
            ],
            'airtel_money' => [
                ProviderCapability::Collection->value => $note,
                ProviderCapability::Disbursement->value => $note,
                ProviderCapability::Status->value => $note,
                ProviderCapability::Webhook->value => $note,
            ],
        ];
    }

    public function run(): void
    {
        $now = now();
        $namespace = 'App\\Providers\\Payment\\';

        foreach (self::PROVIDERS as $code => $p) {
            DB::table('providers')->upsert([[
                'id' => (string) Str::uuid7(),
                'code' => $code,
                'name' => $p['name'],
                'adapter_class' => $namespace.$p['class'],
                'mock_adapter_class' => $namespace.$p['mock'],
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['code'], ['name', 'adapter_class', 'mock_adapter_class', 'updated_at']);
            // mode, is_enabled et health_status sont des réglages d'exploitation : jamais écrasés.

            $providerId = DB::table('providers')->where('code', $code)->value('id');
            $confirmed = $this->confirmed()[$code] ?? [];
            $secondary = $this->secondary()[$code] ?? [];

            foreach (ProviderCapability::cases() as $capability) {
                $isConfirmed = isset($confirmed[$capability->value]);
                DB::table('provider_capabilities')->upsert([[
                    'id' => (string) Str::uuid7(),
                    'provider_id' => $providerId,
                    'capability' => $capability->value,
                    'is_supported' => $isConfirmed,
                    'confirmation_status' => $isConfirmed
                        ? ConfirmationStatus::Confirmed->value
                        : ConfirmationStatus::NotConfirmed->value,
                    'source_reference' => $confirmed[$capability->value] ?? null,
                    'notes' => $secondary[$capability->value]
                        ?? ($isConfirmed ? null : 'NOT CONFIRMED — PROVIDER DOCUMENTATION REQUIRED'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['provider_id', 'capability'], [
                    'is_supported', 'confirmation_status', 'source_reference', 'notes', 'updated_at',
                ]);
            }
        }
    }
}
