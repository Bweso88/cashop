<?php

namespace Database\Seeders\Reference;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\LedgerAccountType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comptes système du ledger, un par devise active (docs/DATABASE.md §Ledger).
 * Les comptes wallet et provider_clearing sont créés à la demande (phase 5).
 */
class SystemLedgerAccountSeeder extends Seeder
{
    public const ACCOUNTS = [
        // code => [type, solde normal]
        'in_flight' => [LedgerAccountType::Liability, EntryDirection::Credit],
        'fee_revenue' => [LedgerAccountType::Revenue, EntryDirection::Credit],
        'fx_revenue' => [LedgerAccountType::Revenue, EntryDirection::Credit],
        'refunds_payable' => [LedgerAccountType::Liability, EntryDirection::Credit],
        'suspense' => [LedgerAccountType::Liability, EntryDirection::Credit],
        'fx_position' => [LedgerAccountType::Asset, EntryDirection::Debit],
    ];

    public function run(): void
    {
        $now = now();
        $currencies = DB::table('currencies')->where('is_active', true)->pluck('code');

        foreach ($currencies as $currency) {
            foreach (self::ACCOUNTS as $name => [$type, $normal]) {
                DB::table('ledger_accounts')->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'code' => "{$name}:{$currency}",
                    'type' => $type->value,
                    'currency_code' => $currency,
                    'normal_balance' => $normal->value,
                    'owner_type' => 'system',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
