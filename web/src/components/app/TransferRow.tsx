import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { StatusBadge } from "@/components/transfers/StatusBadge";
import { countryFlag, dateTime, PAYOUT_LABELS } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import type { Transfer } from "@/lib/transfers";

/** Un envoi échoué ou remboursé n'a rien coûté : son montant n'est pas affiché comme un débit. */
const NOT_DEBITED = ["FAILED", "CANCELLED", "REFUNDED"];

export function TransferRow({ transfer: t }: { transfer: Transfer }) {
  const debited = !NOT_DEBITED.includes(t.status);
  return (
    <Link href={`/transactions/${t.id}`} className="flex items-center gap-4 rounded-xl px-2 py-3 transition hover:bg-surface-2">
      <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-surface-3 text-text">
        <ArrowUpRight className="h-5 w-5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate font-medium">{countryFlag(t.destination_country)} {t.beneficiary.name}</p>
        <p className="truncate text-sm text-muted">
          {t.network.name} · {PAYOUT_LABELS[t.payout_method]} · {dateTime.format(new Date(t.created_at))}
        </p>
      </div>
      <div className="text-right">
        <p className={debited ? "font-semibold tabular-nums" : "font-semibold tabular-nums text-faint line-through"}>
          {debited ? formatMoney(-t.total_debit.amount, t.total_debit.currency, { signed: true }) : formatMoney(t.total_debit.amount, t.total_debit.currency)}
        </p>
        <StatusBadge status={t.status} label={t.status_label} className="mt-1" />
      </div>
    </Link>
  );
}
