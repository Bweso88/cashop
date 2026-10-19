import type { Metadata } from "next";
import { Suspense } from "react";
import { PageHeader } from "@/components/app/PageHeader";
import { TransactionList } from "@/components/transactions/TransactionList";
import { SimulationBadge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { getTransfers, type TransferFilter } from "@/lib/transfers";

export const metadata: Metadata = { title: "Transactions" };

type Search = Promise<{ state?: string; q?: string }>;

async function List({ searchParams }: { searchParams: Search }) {
  const params = await searchParams;
  const state: TransferFilter = params.state === "in_progress" || params.state === "completed" ? params.state : "all";
  const q = (params.q ?? "").slice(0, 40);
  const { data } = await getTransfers(state, q, 50);
  return <TransactionList transfers={data} state={state} q={q} />;
}

export default function TransactionsPage({ searchParams }: { searchParams: Search }) {
  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="Transactions" subtitle="Vos envois, leur statut et leurs reçus." action={<SimulationBadge />} />
      <Card>
        <Suspense fallback={<div className="h-64 animate-pulse rounded-2xl bg-surface-2" />}>
          <List searchParams={searchParams} />
        </Suspense>
      </Card>
    </div>
  );
}
