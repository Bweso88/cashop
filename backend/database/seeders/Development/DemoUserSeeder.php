<?php

namespace Database\Seeders\Development;

use App\Domain\Kyc\Enums\KycStatus;
use App\Domain\Payments\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes de démonstration — local et testing uniquement. Personnes FICTIVES.
 * Mot de passe commun : DEMO_PASSWORD. PIN de transaction des clients : DEMO_PIN.
 * Ces valeurs sont publiques (documentées) : ne jamais les réutiliser ailleurs.
 */
class DemoUserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Cashop-Demo-2026!';

    public const DEMO_PIN = '482915';

    /** [e-mail, téléphone, rôle, prénom, nom, niveau KYC] */
    public const USERS = [
        ['client@cashop.test', '+242060000001', 'customer', 'Amina', 'Nkoulou', 'LEVEL_1'],
        ['client2@cashop.test', '+242050000002', 'customer', 'Grâce', 'Mabiala', 'LEVEL_0'],
        ['support@cashop.test', null, 'support', 'Sam', 'Support', null],
        ['compliance@cashop.test', null, 'compliance', 'Chloé', 'Conformité', null],
        ['finance@cashop.test', null, 'finance', 'Fabrice', 'Finance', null],
        ['admin@cashop.test', null, 'super_admin', 'Ada', 'Admin', null],
    ];

    /** Bénéficiaires fictifs de client@cashop.test : [prénom, nom, pays, téléphone, ville]. */
    public const BENEFICIARIES = [
        ['Paul', 'Mbarga', 'CM', '+237690000101', 'Douala'],
        ['Aminata', 'Kouassi', 'CI', '+2250700000102', 'Abidjan'],
        ['Jean', 'Dupont', 'FR', '+33600000103', 'Paris'],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::USERS as [$email, $phone, $role, $first, $last, $kycLevel]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::create(['email' => $email, 'phone_e164' => $phone, 'password' => self::DEMO_PASSWORD]);
            $user->forceFill(['email_verified_at' => $now, 'phone_verified_at' => $phone ? $now : null])->save();
            $user->assignRole($role);

            // Via le modèle : les champs nominatifs passent par le cast "encrypted".
            $user->profile()->create([
                'first_name' => $first,
                'last_name' => $last,
                'country_of_residence' => 'CG',
                'nationality' => 'CG',
                'preferred_currency' => 'XAF',
            ]);

            if ($role !== 'customer') {
                continue;
            }

            $user->forceFill(['transaction_pin_hash' => Hash::make(self::DEMO_PIN)])->save();
            $user->kycProfile()->create(['level_code' => $kycLevel, 'status' => KycStatus::Verified, 'verified_at' => $now]);

            if ($email !== 'client@cashop.test') {
                continue;
            }
            foreach (self::BENEFICIARIES as [$bFirst, $bLast, $country, $bPhone, $city]) {
                Beneficiary::create([
                    'user_id' => $user->id,
                    'first_name' => $bFirst,
                    'last_name' => $bLast,
                    'phone_e164' => $bPhone,
                    'country_code' => $country,
                    'city' => $city,
                    'identity_hash' => Beneficiary::identityHash($bFirst, $bLast, $country, $bPhone),
                    'display_name' => Beneficiary::displayNameFor($bFirst, $bLast),
                ]);
            }
        }
    }
}
