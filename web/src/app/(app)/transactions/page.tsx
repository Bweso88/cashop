import type { Metadata } from "next";
import { PageHeader } from "@/components/app/PageHeader";
import { TransactionList } from "@/components/transactions/TransactionList";
import { DemoBadge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { DEMO_TRANSFERS } from "@/lib/demo-data";

export const metadata: Metadata = { title: "Transactions" };

export default function TransactionsPage() {
  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="Transactions" subtitle="Tous vos envois et réceptions." action={<DemoBadge />} />
      <Card>
        <TransactionList transfers={DEMO_TRANSFERS} />
      </Card>
    </div>
  );
}
