<?php

namespace Database\Seeders\Development;

use App\Domain\Kyc\Enums\KycStatus;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\LedgerAccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comptes de démonstration (mot de passe commun : DEMO_PASSWORD).
 * Les wallets démarrent à zéro : un solde ne peut naître que d'écritures comptables (phase 5).
 */
class DemoUserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Cashop-Demo-2026!';

    public const USERS = [
        ['client@cashop.test', '+237690000001', 'customer', 'Amina', 'Nkoulou', 'CM', 'LEVEL_1'],
        ['client.fr@cashop.test', '+33600000001', 'customer', 'Jennifer', 'Jenson', 'FR', 'LEVEL_2'],
        ['support@cashop.test', null, 'support', 'Sam', 'Support', 'FR', null],
        ['compliance@cashop.test', null, 'compliance', 'Chloé', 'Conformité', 'FR', null],
        ['finance@cashop.test', null, 'finance', 'Fabrice', 'Finance', 'FR', null],
        ['admin@cashop.test', null, 'super_admin', 'Ada', 'Admin', 'FR', null],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::USERS as [$email, $phone, $role, $first, $last, $country, $kycLevel]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::create([
                'email' => $email,
                'phone_e164' => $phone,
                'password' => self::DEMO_PASSWORD,
            ]);
            $user->forceFill(['email_verified_at' => $now, 'phone_verified_at' => $phone ? $now : null])->save();
            $user->assignRole($role);

            DB::table('user_profiles')->insert([
                'user_id' => $user->id,
                'first_name' => encrypt($first),
                'last_name' => encrypt($last),
                'country_of_residence' => $country,
                'nationality' => $country,
                'preferred_currency' => DB::table('countries')->where('code', $country)->value('default_currency'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($role !== 'customer') {
                continue;
            }

            DB::table('kyc_profiles')->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'level_code' => $kycLevel,
                'status' => KycStatus::Verified->value,
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $walletId = (string) Str::uuid7();
            DB::table('wallets')->insert([
                'id' => $walletId,
                'user_id' => $user->id,
                'label' => 'Portefeuille principal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['XAF', 'EUR'] as $currency) {
                $accountId = (string) Str::uuid7();
                DB::table('ledger_accounts')->insert([
                    'id' => $accountId,
                    'code' => "wallet:{$walletId}:{$currency}",
                    'type' => LedgerAccountType::Liability->value,
                    'currency_code' => $currency,
                    'normal_balance' => EntryDirection::Credit->value,
                    'owner_type' => 'wallet',
                    'owner_id' => $walletId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('wallet_balances')->insert([
                    'id' => (string) Str::uuid7(),
                    'wallet_id' => $walletId,
                    'currency_code' => $currency,
                    'ledger_account_id' => $accountId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
