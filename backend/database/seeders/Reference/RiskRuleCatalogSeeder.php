<?php

namespace Database\Seeders\Reference;

use App\Domain\Risk\Enums\RiskAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catalogue des règles de risque (docs/KYC-AML.md §4). Toutes INACTIVES et sans seuil :
 * les paramètres sont calibrés par la conformité avant activation.
 */
class RiskRuleCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rules = [
            ['VELOCITY', 'Vélocité des transferts', RiskAction::Review],
            ['AMOUNT_THRESHOLD', 'Seuil de montant', RiskAction::Review],
            ['DUPLICATE', 'Transaction dupliquée', RiskAction::StepUpAuth],
            ['NEW_DEVICE', 'Nouvel appareil ou identifiants modifiés récemment', RiskAction::StepUpAuth],
            ['GEO_MISMATCH', 'Incohérence géographique', RiskAction::Review],
            ['SANCTIONS_HIT', 'Correspondance liste de sanctions', RiskAction::Block],
            ['STRUCTURING', 'Fractionnement sous un seuil', RiskAction::Review],
        ];

        foreach ($rules as [$code, $name, $action]) {
            DB::table('risk_rules')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'code' => $code,
                'name' => $name,
                'parameters' => '{}',
                'action' => $action->value,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
