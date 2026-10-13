import Link from "next/link";
import { ArrowDownLeft, ArrowUpRight } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { countryFlag, PAYOUT_LABELS, PROVIDER_LABELS, type DemoTransfer, type TransferStatus } from "@/lib/demo-data";
import { formatMoney } from "@/lib/money";

export const STATUS: Record<TransferStatus, { label: string; tone: "brand" | "warning" | "danger" | "info" | "neutral" }> = {
  COMPLETED: { label: "Livré", tone: "brand" },
  PROCESSING: { label: "En cours", tone: "info" },
  PENDING: { label: "En attente", tone: "warning" },
  FAILED: { label: "Échoué · remboursé", tone: "danger" },
  REFUNDED: { label: "Remboursé", tone: "neutral" },
};

const dateFormat = new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });

export function TransferRow({ transfer }: { transfer: DemoTransfer }) {
  const out = transfer.direction === "out";
  const Icon = out ? ArrowUpRight : ArrowDownLeft;
  const status = STATUS[transfer.status];

  return (
    <Link href={`/transactions/${transfer.id}`} className="flex items-center gap-4 rounded-xl px-2 py-3 transition hover:bg-surface-2">
      <span className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${out ? "bg-surface-3 text-text" : "bg-brand/15 text-brand"}`}>
        <Icon className="h-5 w-5" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate font-medium">
          {countryFlag(transfer.country)} {transfer.counterparty}
        </p>
        <p className="truncate text-sm text-muted">
          {PROVIDER_LABELS[transfer.provider]} · {PAYOUT_LABELS[transfer.payoutMethod]} · {dateFormat.format(new Date(transfer.createdAt))}
        </p>
      </div>
      <div className="text-right">
        <p className={`font-semibold tabular-nums ${out ? "" : "text-brand"}`}>
          {formatMoney(out ? -transfer.sendAmount : transfer.receiveAmount, out ? transfer.sendCurrency : transfer.receiveCurrency, { signed: true })}
        </p>
        <Badge tone={status.tone} className="mt-1">{status.label}</Badge>
      </div>
    </Link>
  );
}
