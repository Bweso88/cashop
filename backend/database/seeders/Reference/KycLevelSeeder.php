<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Structure des niveaux KYC (docs/KYC-AML.md). Les exigences sont un exemple de structure,
 * à valider par la conformité. Les plafonds ne sont PAS définis ici (voir limit_rules).
 */
class KycLevelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $levels = [
            ['LEVEL_0', 'Compte créé', 0, ['phone_verified'], 'Téléphone vérifié par OTP.'],
            ['LEVEL_1', 'Identité vérifiée', 1, ['phone_verified', 'identity_document', 'selfie', 'liveness', 'sanctions_screening'],
                "Pièce d'identité, selfie, contrôle du vivant, filtrage sanctions/PEP."],
            ['LEVEL_2', 'Identité et domicile vérifiés', 2, ['phone_verified', 'identity_document', 'selfie', 'liveness', 'sanctions_screening', 'proof_of_address'],
                'LEVEL_1 + justificatif de domicile (+ source des fonds si requis par la conformité).'],
        ];

        DB::table('kyc_levels')->upsert(array_map(fn ($l) => [
            'code' => $l[0],
            'name' => $l[1],
            'rank' => $l[2],
            'requirements' => json_encode($l[3]),
            'description' => $l[4],
            'created_at' => $now,
            'updated_at' => $now,
        ], $levels), ['code'], ['name', 'rank', 'requirements', 'description', 'updated_at']);
    }
}
