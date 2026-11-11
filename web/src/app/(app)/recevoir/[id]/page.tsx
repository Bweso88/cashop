import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { CheckCircle2, ChevronLeft, ShieldAlert } from "lucide-react";
import { IncomingTracker } from "@/components/receive/IncomingTracker";
import { Badge, SimulationBadge } from "@/components/ui/Badge";
import { Card } from "@/components/ui/Card";
import { ApiError } from "@/lib/api/client";
import { countryFlag, countryName, dateTime } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { getIncomingTransfer, type IncomingTransfer } from "@/lib/transfers";

export const metadata: Metadata = { title: "Réception" };

async function Detail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  let t: IncomingTransfer;
  try {
    t = await getIncomingTransfer(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
  const paid = t.status === "PAID_OUT";

  return (
    <Card className="space-y-6 p-6">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="text-sm text-muted">Reçu de</p>
          <p className="mt-1 truncate text-xl font-semibold">{countryFlag(t.sender.country)} {t.sender.name}</p>
        </div>
        <Badge tone={paid ? "brand" : t.status === "FAILED" ? "danger" : t.status === "REQUIRES_RECONCILIATION" ? "warning" : "info"}>{t.status_label}</Badge>
      </div>
      <div>
        <p className="text-4xl font-semibold tabular-nums">{formatMoney(t.amount.amount, t.amount.currency)}</p>
        <p className="mt-1 text-muted">{paid ? "versés" : t.status === "FAILED" ? "non versés" : "à verser"} sur {t.payout.provider.name} · {t.payout.msisdn_masked}</p>
      </div>

      {paid && (
        <div className="flex gap-3 rounded-2xl border border-brand/30 bg-brand/10 p-4 text-sm">
          <CheckCircle2 className="h-5 w-5 shrink-0 text-brand" />
          <p>{t.network.name} a versé l&apos;argent sur votre compte {t.payout.provider.name}{t.paid_out_at ? ` le ${dateTime.format(new Date(t.paid_out_at))}` : ""}.</p>
        </div>
      )}
      {t.status === "FAILED" && (
        <div className="flex gap-3 rounded-2xl border border-danger/30 bg-danger/10 p-4 text-sm">
          <ShieldAlert className="h-5 w-5 shrink-0 text-danger" />
          <p>{t.failure?.message ?? "Versement refusé par le réseau."} Le transfert reste chez {t.network.name} : contactez l&apos;expéditeur ou une agence.</p>
        </div>
      )}
      {!t.is_terminal && <IncomingTracker id={t.id} status={t.status} />}

      <dl className="divide-y divide-line text-sm">
        {[
          ["Réseau", t.network.name],
          ["Référence du transfert", t.network_reference],
          ["Référence du versement", t.payout.reference ?? "—"],
          ["Pays d'envoi", countryName(t.sender.country)],
          ["Demandé le", dateTime.format(new Date(t.created_at))],
        ].map(([k, v]) => (
          <div key={k} className="flex justify-between gap-4 py-3">
            <dt className="text-muted">{k}</dt>
            <dd className="break-all text-right font-medium">{v}</dd>
          </div>
        ))}
      </dl>
      {t.simulation_notice && <p className="text-xs text-warning">{t.simulation_notice}</p>}
    </Card>
  );
}

export default function IncomingPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <div className="mx-auto max-w-2xl">
      <div className="mb-6 flex items-center justify-between">
        <Link href="/recevoir" className="inline-flex items-center gap-1 text-sm text-muted hover:text-text"><ChevronLeft className="h-4 w-4" /> Recevoir</Link>
        <SimulationBadge />
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <Detail params={params} />
      </Suspense>
    </div>
  );
}
