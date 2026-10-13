import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ChevronLeft } from "lucide-react";
import { STATUS } from "@/components/app/TransferRow";
import { Badge, DemoBadge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { countryFlag, countryName, DEMO_TRANSFERS, PAYOUT_LABELS, PROVIDER_LABELS } from "@/lib/demo-data";
import { formatMoney } from "@/lib/money";

export const metadata: Metadata = { title: "Détail de la transaction" };

const dateTime = new Intl.DateTimeFormat("fr-FR", { dateStyle: "long", timeStyle: "short" });

async function TransferDetail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const t = DEMO_TRANSFERS.find((x) => x.id === id);
  if (!t) notFound();
  const status = STATUS[t.status];
  const out = t.direction === "out";

  return (
    <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
      <Card className="p-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm text-muted">{out ? "Envoyé à" : "Reçu de"}</p>
            <p className="mt-1 text-xl font-semibold">{countryFlag(t.country)} {t.counterparty}</p>
          </div>
          <Badge tone={status.tone}>{status.label}</Badge>
        </div>
        <p className="mt-6 text-4xl font-semibold tabular-nums">{formatMoney(out ? -t.sendAmount : t.receiveAmount, out ? t.sendCurrency : t.receiveCurrency, { signed: true })}</p>
        {out && <p className="mt-1 text-muted">Reçu : {formatMoney(t.receiveAmount, t.receiveCurrency)}</p>}

        <dl className="mt-8 divide-y divide-line text-sm">
          {[
            ["Partenaire", PROVIDER_LABELS[t.provider]],
            ["Mode de réception", PAYOUT_LABELS[t.payoutMethod]],
            ["Pays", countryName(t.country)],
            ["Frais", formatMoney(t.fees, t.sendCurrency)],
            ["Taux appliqué", `1 ${t.sendCurrency} = ${Number(t.fxRate).toLocaleString("fr-FR")} ${t.receiveCurrency}`],
            ["Référence", t.reference],
            ["Identifiant", t.id],
          ].map(([k, v]) => (
            <div key={k} className="flex justify-between gap-4 py-3">
              <dt className="text-muted">{k}</dt>
              <dd className="break-all text-right font-medium">{v}</dd>
            </div>
          ))}
        </dl>
      </Card>

      <Card className="p-6">
        <CardHeader title="Suivi" subtitle={dateTime.format(new Date(t.createdAt))} />
        <ol className="relative ml-2 border-l border-line">
          {t.timeline.map((e, i) => (
            <li key={i} className="mb-6 ml-6 last:mb-0">
              <span className={`absolute -left-[7px] mt-1 h-3.5 w-3.5 rounded-full ring-4 ring-surface ${e.status === "FAILED" ? "bg-danger" : "bg-brand"}`} />
              <p className="font-medium">{e.label}</p>
              <p className="text-xs text-muted">{dateTime.format(new Date(e.at))} · {e.status}</p>
            </li>
          ))}
        </ol>
      </Card>
    </div>
  );
}

export default function TransferPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex items-center justify-between">
        <Link href="/transactions" className="inline-flex items-center gap-1 text-sm text-muted hover:text-text">
          <ChevronLeft className="h-4 w-4" /> Transactions
        </Link>
        <DemoBadge />
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <TransferDetail params={params} />
      </Suspense>
    </div>
  );
}
