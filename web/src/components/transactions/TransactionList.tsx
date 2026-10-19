import Link from "next/link";
import { TransferRow } from "@/components/app/TransferRow";
import { cn } from "@/lib/cn";
import type { Transfer, TransferFilter } from "@/lib/transfers";

const FILTERS: { key: TransferFilter; label: string }[] = [
  { key: "all", label: "Tous" },
  { key: "in_progress", label: "En cours" },
  { key: "completed", label: "Terminés" },
];

/** Filtres et recherche dans l'URL (?state=…&q=…) : la liste est rendue côté serveur. */
export function TransactionList({ transfers, state, q }: { transfers: Transfer[]; state: TransferFilter; q: string }) {
  return (
    <>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        {FILTERS.map((f) => (
          <Link key={f.key} href={{ pathname: "/transactions", query: { state: f.key, ...(q ? { q } : {}) } }} className={cn("rounded-full px-4 py-2 text-sm font-medium transition", state === f.key ? "bg-brand text-ink" : "bg-surface-2 text-muted hover:text-text")}>
            {f.label}
          </Link>
        ))}
        <form action="/transactions" className="ml-auto w-full sm:w-64">
          <input type="hidden" name="state" value={state} />
          <input name="q" defaultValue={q} placeholder="Référence CSH-…" className="w-full rounded-full border border-line bg-surface-2 px-4 py-2 text-sm outline-none focus:border-brand" />
        </form>
      </div>
      <div className="-mx-2 divide-y divide-line">
        {transfers.map((t) => <TransferRow key={t.id} transfer={t} />)}
        {transfers.length === 0 && <p className="px-2 py-10 text-center text-muted">Aucun transfert ne correspond.</p>}
      </div>
    </>
  );
}
