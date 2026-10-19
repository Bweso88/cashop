import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ChevronLeft } from "lucide-react";
import { Logo } from "@/components/brand/Logo";
import { Card } from "@/components/ui/Card";
import { ApiError } from "@/lib/api/client";
import { countryName, dateTime, PAYOUT_LABELS } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { getReceipts, type Receipt } from "@/lib/transfers";

export const metadata: Metadata = { title: "Reçu" };

function ReceiptCard({ r }: { r: Receipt }) {
  const c = r.content;
  const rows: [string, string][] = [
    ["Statut", c.status_label],
    ["Expéditeur", c.sender || "—"],
    ["Bénéficiaire", `${c.beneficiary} · ${countryName(c.destination_country)}`],
    ["Réseau", `${c.network} · ${PAYOUT_LABELS[c.payout_method]}`],
    ["Payé avec", c.funding],
    ["Montant envoyé", formatMoney(c.send_amount, c.send_currency)],
    ...c.fees.map((f): [string, string] => [f.label, formatMoney(f.amount, c.send_currency)]),
    ["Total débité", formatMoney(c.total_debit, c.send_currency)],
    ["Montant reçu", formatMoney(c.receive_amount, c.receive_currency)],
    ...(c.refunded_amount ? [["Montant remboursé", formatMoney(c.refunded_amount, c.send_currency)] as [string, string]] : []),
    ["Référence Cashop", c.external_reference],
    ["Référence réseau", c.provider_reference ?? "—"],
    ...(c.pickup_code && r.kind === "TRANSFER" ? [["Code de retrait", c.pickup_code] as [string, string]] : []),
  ];

  return (
    <Card className="p-6 sm:p-8">
      <div className="flex items-start justify-between gap-4">
        <Logo />
        <div className="text-right text-sm">
          <p className="font-semibold">{r.kind === "REFUND" ? "Reçu de remboursement" : "Reçu de transfert"}</p>
          <p className="font-mono text-muted">{r.number}</p>
          <p className="text-muted">{dateTime.format(new Date(r.issued_at))}</p>
        </div>
      </div>
      {r.is_simulation && (
        <p className="mt-6 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm font-medium text-warning">{c.simulation_notice}</p>
      )}
      <dl className="mt-6 divide-y divide-line text-sm">
        {rows.map(([k, v]) => (
          <div key={k} className="flex justify-between gap-4 py-2.5">
            <dt className="text-muted">{k}</dt>
            <dd className="break-all text-right font-medium">{v}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}

async function Receipts({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  let receipts: Receipt[];
  try {
    receipts = await getReceipts(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    if (error instanceof ApiError && error.status === 409) receipts = [];
    else throw error;
  }
  if (receipts.length === 0) {
    return <Card className="text-center text-muted">Le reçu sera disponible une fois l&apos;opération confirmée par le prestataire.</Card>;
  }
  return <div className="space-y-6">{receipts.map((r) => <ReceiptCard key={r.number} r={r} />)}</div>;
}

export default function ReceiptPage({ params }: { params: Promise<{ id: string }> }) {
  return (
    <div className="mx-auto max-w-2xl">
      <Suspense fallback={<div className="mb-6 h-5" />}>
        <BackLink params={params} />
      </Suspense>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <Receipts params={params} />
      </Suspense>
    </div>
  );
}

async function BackLink({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <Link href={`/transactions/${id}`} className="mb-6 inline-flex items-center gap-1 text-sm text-muted hover:text-text">
      <ChevronLeft className="h-4 w-4" /> Suivi du transfert
    </Link>
  );
}
