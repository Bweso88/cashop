<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Risk\Enums\LimitScope;
use App\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Plafonds par niveau KYC (table limit_rules). Aucune valeur réglementaire n'est codée en dur :
 * sans règle configurée pour le niveau et la devise, l'envoi est refusé.
 */
class LimitChecker
{
    public function assertAllowed(User $user, int $amount, string $currency): void
    {
        $kyc = $user->kycProfile;
        if (! $kyc || $kyc->status->value !== 'VERIFIED') {
            throw new ApiException('KYC_REQUIRED', 'Vérifiez votre numéro de téléphone avant d’envoyer de l’argent.', 403);
        }

        $rules = DB::table('limit_rules')
            ->where('kyc_level_code', $kyc->level_code)->where('currency_code', $currency)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('country_code')->orWhere('country_code', $user->profile?->country_of_residence))
            ->get();
        if ($rules->isEmpty()) {
            throw new ApiException('LIMITS_NOT_CONFIGURED', 'Aucun plafond n’est configuré pour votre niveau de vérification : envoi impossible.', 403);
        }

        foreach ($rules as $rule) {
            $used = match (LimitScope::from($rule->scope)) {
                LimitScope::PerTransaction => 0,
                LimitScope::Daily => $this->usedSince($user, $currency, now()->startOfDay()),
                LimitScope::Monthly => $this->usedSince($user, $currency, now()->startOfMonth()),
            };
            if ($rule->max_amount !== null && $used + $amount > $rule->max_amount) {
                throw new ApiException('LIMIT_EXCEEDED', 'Ce montant dépasse votre plafond autorisé.', 422);
            }
        }
    }

    private function usedSince(User $user, string $currency, \DateTimeInterface $since): int
    {
        return (int) DB::table('transfers')
            ->where('user_id', $user->id)->where('send_currency', $currency)->where('created_at', '>=', $since)
            ->whereNotIn('status', [TransferStatus::Failed->value, TransferStatus::Cancelled->value, TransferStatus::Refunded->value])
            ->sum('send_amount');
    }
}
