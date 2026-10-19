import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ChevronRight, IdCard, Send, Users } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { TransferRow } from "@/components/app/TransferRow";
import { Badge, SimulationBadge } from "@/components/ui/Badge";
import { ButtonLink } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { getCurrentUser } from "@/lib/session";
import { getCatalog, getTransfers } from "@/lib/transfers";

export const metadata: Metadata = { title: "Accueil" };

async function Greeting() {
  const user = await getCurrentUser();
  const verified = user.kyc_status === "VERIFIED" && user.kyc_level !== "LEVEL_0";
  return (
    <PageHeader
      title={`Bonjour ${user.first_name ?? ""}`.trim()}
      subtitle={
        <span className="inline-flex flex-wrap items-center gap-2">
          Envoyez de l&apos;argent depuis le Congo, payé par Mobile Money.
          {verified ? <Badge tone="brand">Identité vérifiée · {user.kyc_level}</Badge> : <Badge tone="warning">Identité à vérifier</Badge>}
        </span>
      }
      action={<SimulationBadge />}
    />
  );
}

async function Recent() {
  const [{ data }, inProgress] = await Promise.all([getTransfers("all", "", 5), getTransfers("in_progress", "", 1)]);
  return (
    <Card>
      <CardHeader
        title="Envois récents"
        subtitle={inProgress.meta.total > 0 ? `${inProgress.meta.total} en cours` : undefined}
        action={<Link href="/transactions" className="text-sm font-medium text-brand hover:underline">Tout voir</Link>}
      />
      <div className="-mx-2 divide-y divide-line">
        {data.map((t) => <TransferRow key={t.id} transfer={t} />)}
        {data.length === 0 && <p className="px-2 py-8 text-center text-muted">Aucun envoi pour l&apos;instant.</p>}
      </div>
    </Card>
  );
}

async function Partners() {
  const { funding, networks } = await getCatalog();
  return (
    <Card>
      <CardHeader title="Partenaires" subtitle="Paiement et réseaux de transfert" />
      <ul className="space-y-2 text-sm">
        {[...funding, ...networks].map((p) => (
          <li key={p.code} className="flex items-center justify-between gap-3">
            <span>{p.name}</span>
            <Badge tone={p.is_simulation ? "warning" : "info"}>{p.is_simulation ? "Simulation" : p.environment}</Badge>
          </li>
        ))}
      </ul>
    </Card>
  );
}

const skeleton = (h: string) => <div className={`${h} animate-pulse rounded-3xl bg-surface`} />;

export default function DashboardPage() {
  return (
    <div className="mx-auto max-w-6xl">
      <Suspense fallback={<div className="mb-6 h-14 w-72 animate-pulse rounded-xl bg-surface-2" />}>
        <Greeting />
      </Suspense>

      <div className="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
        <div className="min-w-0 space-y-6">
          <Card className="card-gradient-brand overflow-hidden p-6 text-white">
            <p className="text-sm text-white/80">Cashop ne garde pas votre argent</p>
            <p className="mt-2 max-w-md text-2xl font-semibold leading-snug">Payez avec MTN Mobile Money ou Airtel Money, votre proche reçoit via Western Union, MoneyGram ou Ria.</p>
            <div className="mt-6 flex flex-wrap gap-3">
              <ButtonLink href="/envoyer" className="bg-white text-ink hover:bg-white/90"><Send className="h-4 w-4" /> Envoyer de l&apos;argent</ButtonLink>
              <ButtonLink href="/beneficiaires" variant="ghost" className="border-white/40 text-white hover:bg-white/10"><Users className="h-4 w-4" /> Bénéficiaires</ButtonLink>
            </div>
          </Card>
          <Suspense fallback={skeleton("h-64")}>
            <Recent />
          </Suspense>
        </div>

        <div className="min-w-0 space-y-6">
          <Link href="/kyc" className="flex items-center gap-4 rounded-2xl border border-brand/30 bg-brand/10 p-4 transition hover:bg-brand/15">
            <IdCard className="h-6 w-6 shrink-0 text-brand" />
            <div className="flex-1">
              <p className="font-medium">Vos plafonds dépendent de votre vérification d&apos;identité</p>
              <p className="text-sm text-muted">Consultez votre niveau et ses limites.</p>
            </div>
            <ChevronRight className="h-5 w-5 text-muted" />
          </Link>
          <Suspense fallback={skeleton("h-56")}>
            <Partners />
          </Suspense>
          <Card className="text-sm text-muted">
            <p className="mb-1 font-medium text-text">Mode simulation</p>
            Aucun fonds réel n&apos;est déplacé. Les références « SIM- » ne sont reconnues par aucun réseau.
          </Card>
        </div>
      </div>
    </div>
  );
}
