<?php

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\LedgerAccountType;
use App\Domain\Ledger\Enums\LedgerTransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Écritures en partie double. PostgreSQL refuse toute transaction déséquilibrée (trigger différé)
 * et toute modification ultérieure. Idempotent : une même clé n'est comptabilisée qu'une fois.
 *
 * Comptes utilisés par Cashop (logiciel d'orchestration, sans portefeuille client) :
 *  - collection_clearing:{opérateur}:{devise} (ACTIF)  fonds collectés sur le compte marchand Mobile Money
 *  - in_flight:{devise} (PASSIF)                       dû au client tant que son transfert n'est pas livré ou remboursé
 *  - remittance_payable:{réseau}:{devise} (PASSIF)     dû au réseau de transfert
 *  - fee_revenue:{devise} (PRODUIT)                    frais de service
 */
class LedgerService
{
    /**
     * @param  list<array{0: string, 1: EntryDirection, 2: int}>  $lines  [code compte, sens, montant]
     */
    public function post(LedgerTransactionType $type, string $idempotencyKey, string $currency, string $referenceType, string $referenceId, string $description, array $lines): void
    {
        DB::transaction(function () use ($type, $idempotencyKey, $currency, $referenceType, $referenceId, $description, $lines) {
            if (DB::table('ledger_transactions')->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }
            $txId = (string) Str::uuid7();
            $now = now();
            DB::table('ledger_transactions')->insert([
                'id' => $txId, 'type' => $type->value, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey, 'description' => $description, 'posted_at' => $now, 'created_at' => $now,
            ]);
            foreach ($lines as [$code, $direction, $amount]) {
                if ($amount <= 0) {
                    continue;
                }
                DB::table('ledger_entries')->insert([
                    'id' => (string) Str::uuid7(), 'ledger_transaction_id' => $txId, 'ledger_account_id' => $this->account($code, $currency),
                    'direction' => $direction->value, 'amount' => $amount, 'currency_code' => $currency, 'created_at' => $now,
                ]);
            }
        });
    }

    /** Solde d'un compte dans son sens normal (débit pour un actif, crédit pour un passif). */
    public function balance(string $code): int
    {
        $account = DB::table('ledger_accounts')->where('code', $code)->first();
        if (! $account) {
            return 0;
        }
        $sums = DB::table('ledger_entries')->where('ledger_account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'DEBIT' THEN amount ELSE 0 END), 0) AS debit, COALESCE(SUM(CASE WHEN direction = 'CREDIT' THEN amount ELSE 0 END), 0) AS credit")
            ->first();

        return $account->normal_balance === EntryDirection::Debit->value ? $sums->debit - $sums->credit : $sums->credit - $sums->debit;
    }

    private function account(string $code, string $currency): string
    {
        $existing = DB::table('ledger_accounts')->where('code', $code)->value('id');
        if ($existing) {
            return $existing;
        }
        [$type, $normal] = match (explode(':', $code)[0]) {
            'collection_clearing' => [LedgerAccountType::Asset, EntryDirection::Debit],
            'fee_revenue', 'fx_revenue' => [LedgerAccountType::Revenue, EntryDirection::Credit],
            default => [LedgerAccountType::Liability, EntryDirection::Credit],
        };
        $id = (string) Str::uuid7();
        DB::table('ledger_accounts')->insertOrIgnore([
            'id' => $id, 'code' => $code, 'type' => $type->value, 'currency_code' => $currency, 'normal_balance' => $normal->value,
            'owner_type' => 'system', 'is_system' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('ledger_accounts')->where('code', $code)->value('id');
    }
}
