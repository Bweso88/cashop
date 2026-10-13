<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\Receipt;
use App\Domain\Payments\Models\Transfer;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Reçus figés : émis une seule fois par transfert et par nature (TRANSFER, REFUND).
 * Un reçu de simulation porte la mention explicite « simulation — aucun fonds réel versé ».
 */
class ReceiptService
{
    public const SIMULATION_NOTICE = 'SIMULATION — aucun fonds réel versé. Les références « SIM- » ne sont pas reconnues par les réseaux de transfert.';

    public function issue(Transfer $transfer, string $kind): Receipt
    {
        if ($existing = Receipt::query()->where('transfer_id', $transfer->id)->where('kind', $kind)->first()) {
            return $existing;
        }
        $transfer->loadMissing('provider', 'fundingProvider', 'beneficiary', 'user.profile', 'quote');

        $payload = [
            'kind' => $kind,
            'status' => $transfer->status->value,
            'status_label' => $transfer->status->label(),
            'environment' => $transfer->environment->value,
            'simulation_notice' => $transfer->isSimulated() ? self::SIMULATION_NOTICE : null,
            'sender' => trim(($transfer->user->profile?->first_name ?? '').' '.($transfer->user->profile?->last_name ?? '')),
            'beneficiary' => $transfer->beneficiary->fullName(),
            'destination_country' => $transfer->destination_country,
            'network' => $transfer->provider->name,
            'funding' => $transfer->fundingProvider->name,
            'payout_method' => $transfer->payout_method->value,
            'send_amount' => $transfer->send_amount,
            'send_currency' => $transfer->send_currency,
            'fees' => $transfer->quote->fee_breakdown,
            'total_fees' => $transfer->total_fees,
            'total_debit' => $transfer->total_debit,
            'receive_amount' => $transfer->receive_amount,
            'receive_currency' => $transfer->receive_currency,
            'fx_rate' => (string) $transfer->fx_rate,
            'external_reference' => $transfer->external_reference,
            'provider_reference' => $transfer->provider_reference,
            'pickup_code' => $transfer->pickup_code,
            'refunded_amount' => $kind === 'REFUND' ? $transfer->total_debit : null,
            'created_at' => $transfer->created_at->toIso8601String(),
        ];

        try {
            return Receipt::create([
                'transfer_id' => $transfer->id,
                'number' => ($kind === 'REFUND' ? 'RB-' : 'RC-').now()->format('Ymd').'-'.substr($transfer->external_reference, 4),
                'kind' => $kind,
                'is_simulation' => $transfer->isSimulated(),
                'payload' => $payload,
                'issued_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Émission concurrente : on renvoie le reçu déjà créé.
            return Receipt::query()->where('transfer_id', $transfer->id)->where('kind', $kind)->firstOrFail();
        }
    }
}
