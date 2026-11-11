import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ArrowDownLeft } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { ReceiveFlow } from "@/components/receive/ReceiveFlow";
import { Badge, SimulationBadge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { dateTime } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { getCurrentUser } from "@/lib/session";
import { getCatalog, getIncomingTransfers, type IncomingStatus } from "@/lib/transfers";

export const metadata: Metadata = { title: "Recevoir de l'argent" };

const TONE: Record<IncomingStatus, "brand" | "info" | "danger" | "warning"> = {
  PAID_OUT: "brand", PAYOUT_PENDING: "info", FAILED: "danger", REQUIRES_RECONCILIATION: "warning",
};

async function Flow() {
  const [user, catalog] = await Promise.all([getCurrentUser(), getCatalog()]);
  return (
    <ReceiveFlow
      networks={catalog.networks.map(({ code, name }) => ({ code, name }))}
      wallets={catalog.funding}
      simulation={catalog.scenarios.length > 0}
      defaultMsisdn={user.phone?.startsWith("+242") ? user.phone : ""}
    />
  );
}

async function History() {
  const items = await getIncomingTransfers();
  return (
    <Card>
      <CardHeader title="Vos réceptions" />
      <div className="-mx-2 divide-y divide-line">
        {items.map((t) => (
          <Link key={t.id} href={`/recevoir/${t.id}`} className="flex items-center gap-4 rounded-xl px-2 py-3 transition hover:bg-surface-2">
            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand/15 text-brand"><ArrowDownLeft className="h-5 w-5" /></span>
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium">{t.sender.name}</p>
              <p className="truncate text-sm text-muted">{t.network.name} → {t.payout.provider.name} · {dateTime.format(new Date(t.created_at))}</p>
            </div>
            <div className="text-right">
              <p className={t.status === "PAID_OUT" ? "font-semibold tabular-nums text-brand" : "font-semibold tabular-nums text-muted"}>
                {t.status === "PAID_OUT" ? "+" : ""}{formatMoney(t.amount.amount, t.amount.currency)}
              </p>
              <Badge tone={TONE[t.status]} className="mt-1">{t.status_label}</Badge>
            </div>
          </Link>
        ))}
        {items.length === 0 && <p className="px-2 py-8 text-center text-muted">Aucune réception pour l&apos;instant.</p>}
      </div>
    </Card>
  );
}

export default function ReceivePage() {
  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <PageHeader title="Recevoir de l'argent" subtitle="Un proche vous a envoyé de l'argent par Western Union, MoneyGram ou Ria : recevez-le sur votre Mobile Money." action={<SimulationBadge />} />
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <Flow />
      </Suspense>
      <Suspense fallback={<div className="h-40 animate-pulse rounded-3xl bg-surface" />}>
        <History />
      </Suspense>
    </div>
  );
}
