"use client";

import { useState } from "react";
import { TransferRow } from "@/components/app/TransferRow";
import { cn } from "@/lib/cn";
import type { DemoTransfer } from "@/lib/demo-data";

const FILTERS = [
  { key: "all", label: "Toutes", match: () => true },
  { key: "out", label: "Envoyées", match: (t: DemoTransfer) => t.direction === "out" },
  { key: "in", label: "Reçues", match: (t: DemoTransfer) => t.direction === "in" },
  { key: "pending", label: "En cours", match: (t: DemoTransfer) => ["PENDING", "PROCESSING"].includes(t.status) },
  { key: "failed", label: "Échouées", match: (t: DemoTransfer) => t.status === "FAILED" },
] as const;

export function TransactionList({ transfers }: { transfers: DemoTransfer[] }) {
  const [filter, setFilter] = useState<(typeof FILTERS)[number]["key"]>("all");
  const [query, setQuery] = useState("");
  const active = FILTERS.find((f) => f.key === filter)!;
  const visible = transfers.filter((t) => active.match(t) && t.counterparty.toLowerCase().includes(query.toLowerCase()));

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        {FILTERS.map((f) => (
          <button key={f.key} onClick={() => setFilter(f.key)} className={cn("rounded-full px-4 py-2 text-sm font-medium transition", filter === f.key ? "bg-brand text-ink" : "bg-surface-2 text-muted hover:text-text")}>
            {f.label}
          </button>
        ))}
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Rechercher un bénéficiaire…" className="ml-auto w-full rounded-full border border-line bg-surface-2 px-4 py-2 text-sm outline-none focus:border-brand sm:w-64" />
      </div>
      <div className="-mx-2 divide-y divide-line">
        {visible.map((t) => (
          <TransferRow key={t.id} transfer={t} />
        ))}
        {visible.length === 0 && <p className="px-2 py-10 text-center text-muted">Aucune transaction ne correspond.</p>}
      </div>
    </>
  );
}
