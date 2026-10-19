import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { CheckCircle2, ChevronLeft, FileText, RotateCcw, ShieldAlert } from "lucide-react";
import { StatusBadge } from "@/components/transfers/StatusBadge";
import { TransferTracker } from "@/components/transfers/TransferTracker";
import { SimulationBadge } from "@/components/ui/Badge";
import { ButtonLink } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { ApiError } from "@/lib/api/client";
import { countryFlag, countryName, dateTime, PAYOUT_LABELS } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { getTransfer, type Transfer } from "@/lib/transfers";

export const metadata: Metadata = { title: "Suivi du transfert" };

function Outcome({ t }: { t: Transfer }) {
  if (t.is_delivered) {
    return (
      <div className="flex gap-3 rounded-2xl border border-brand/30 bg-brand/10 p-4 text-sm">
        <CheckCircle2 className="h-5 w-5 shrink-0 text-brand" />
        <div>
          <p className="font-medium">{t.status === "TRANSFER_AVAILABLE_FOR_PICKUP" ? "Disponible au retrait" : "Versé au bénéficiaire"}</p>
          {t.pickup_code && (
            <p className="mt-1 text-muted">Code de retrait à communiquer à {t.beneficiary.name} : <span className="font-mono font-semibold text-text">{t.pickup_code}</span></p>
          )}
        </div>
      </div>
    );
  }
  if (t.status === "REFUNDED" || t.status === "REFUND_PENDING") {
    return (
      <div className="flex gap-3 rounded-2xl border border-line bg-surface-2 p-4 text-sm">
        <RotateCcw className="h-5 w-5 shrink-0 text-muted" />
        <p>
          {t.status === "REFUNDED" ? "Remboursé" : "Remboursement en cours"} : {formatMoney(t.total_debit.amount, t.total_debit.currency)}, frais compris, sur votre compte {t.funding.provider.name}.
          {t.failure?.message && <span className="block text-muted">Motif : {t.failure.message}</span>}
        </p>
      </div>
    );
  }
  if (t.status === "REQUIRES_RECONCILIATION") {
    return (
      <div className="flex gap-3 rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm">
        <ShieldAlert className="h-5 w-5 shrink-0 text-warning" />
        <p>Nous vérifions l&apos;opération auprès des prestataires. Votre argent n&apos;est pas perdu : soit le transfert est livré, soit vous êtes remboursé.</p>
      </div>
    );
  }
  if (t.status === "FAILED") {
    return (
      <div className="flex gap-3 rounded-2xl border border-danger/30 bg-danger/10 p-4 text-sm">
        <ShieldAlert className="h-5 w-5 shrink-0 text-danger" />
        <p>Échec : {t.failure?.message ?? "opération refusée"}. Aucun montant n&apos;a été débité.</p>
      </div>
    );
  }
  return null;
}

/** Le montant mis en avant dépend de l'issue : jamais « reçu » tant que le réseau n'a pas livré. */
function Headline({ t }: { t: Transfer }) {
  const refund = t.status === "REFUNDED" || t.status === "REFUND_PENDING";
  const amount = refund ? t.total_debit : t.receive;
  const caption = t.is_delivered
    ? `reçus via ${t.network.name} · ${PAYOUT_LABELS[t.payout_method]}`
    : refund
      ? `${t.status === "REFUNDED" ? "remboursés" : "en cours de remboursement"} sur votre compte ${t.funding.provider.name}`
      : t.status === "FAILED" || t.status === "CANCELLED"
        ? "non envoyés"
        : `à recevoir via ${t.network.name} · ${PAYOUT_LABELS[t.payout_method]}`;
  return (
    <div>
      <p className="text-4xl font-semibold tabular-nums">{formatMoney(amount.amount, amount.currency)}</p>
      <p className="mt-1 text-muted">{caption}</p>
    </div>
  );
}

async function TransferDetail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  let t: Transfer;
  try {
    t = await getTransfer(id);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
      <Card className="min-w-0 space-y-6 p-6">
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0">
            <p className="text-sm text-muted">Envoi à</p>
            <p className="mt-1 truncate text-xl font-semibold">{countryFlag(t.destination_country)} {t.beneficiary.name}</p>
          </div>
          <StatusBadge status={t.status} label={t.status_label} />
        </div>
        <Headline t={t} />

        <Outcome t={t} />
        {!t.is_terminal && !t.is_delivered && <TransferTracker id={t.id} status={t.status} />}
        {(t.receipts?.length ?? 0) > 0 && (
          <ButtonLink href={`/transactions/${t.id}/recu`} variant="ghost"><FileText className="h-4 w-4" /> Voir le reçu</ButtonLink>
        )}

        <dl className="divide-y divide-line text-sm">
          {[
            ["Référence Cashop", t.external_reference],
            ["Référence réseau", t.provider_reference ?? "—"],
            ["Pays", countryName(t.destination_country)],
            ["Montant envoyé", formatMoney(t.send.amount, t.send.currency)],
            ["Frais", formatMoney(t.total_fees.amount, t.total_fees.currency)],
            ["Total débité", formatMoney(t.total_debit.amount, t.total_debit.currency)],
            ["Payé avec", `${t.funding.provider.name} · ${t.funding.msisdn_masked}`],
            ["Créé le", dateTime.format(new Date(t.created_at))],
          ].map(([k, v]) => (
            <div key={k} className="flex justify-between gap-4 py-3">
              <dt className="text-muted">{k}</dt>
              <dd className="break-all text-right font-medium">{v}</dd>
            </div>
          ))}
        </dl>
        {t.simulation_notice && <p className="text-xs text-warning">{t.simulation_notice}</p>}
      </Card>

      <Card className="min-w-0 p-6">
        <CardHeader title="Suivi" subtitle="Chaque étape confirmée par le prestataire" />
        <ol className="relative ml-2 border-l border-line">
          {(t.timeline ?? []).map((e, i) => (
            <li key={i} className="mb-6 ml-6 last:mb-0">
              <span className={`absolute -left-[7px] mt-1 h-3.5 w-3.5 rounded-full ring-4 ring-surface ${["FAILED", "CANCELLED"].includes(e.to) ? "bg-danger" : e.to === "REQUIRES_RECONCILIATION" ? "bg-warning" : "bg-brand"}`} />
              <p className="font-medium">{e.label}</p>
              <p className="text-xs text-muted">{dateTime.format(new Date(e.at))}</p>
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
        <SimulationBadge />
      </div>
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <TransferDetail params={params} />
      </Suspense>
    </div>
  );
}
