import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { ArrowDownLeft, Banknote, ChevronRight, Globe2, IdCard, Send, Smartphone } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { TransferRow } from "@/components/app/TransferRow";
import { Badge, DemoBadge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { DEMO_TRANSFERS, DEMO_WALLETS, demoTotalInEur } from "@/lib/demo-data";
import { formatMoney } from "@/lib/money";
import { getCurrentUser } from "@/lib/session";

export const metadata: Metadata = { title: "Accueil" };

const ACTIONS = [
  { href: "/envoyer", label: "Envoyer", icon: Send, tone: "bg-brand text-ink" },
  { href: "/recevoir", label: "Recevoir", icon: ArrowDownLeft, tone: "bg-surface-3 text-text" },
  { href: "/envoyer?mode=mobile_money", label: "Mobile Money", icon: Smartphone, tone: "bg-surface-3 text-text" },
  { href: "/envoyer?mode=card", label: "International", icon: Globe2, tone: "bg-surface-3 text-text" },
  { href: "/envoyer?mode=cash_pickup", label: "Retrait cash", icon: Banknote, tone: "bg-surface-3 text-text" },
];

async function Greeting() {
  const user = await getCurrentUser();
  const verified = user.kyc_status === "VERIFIED" && user.kyc_level !== "LEVEL_0";
  return (
    <PageHeader
      title={`Bonjour ${user.first_name ?? ""}`.trim()}
      subtitle={
        <span className="inline-flex flex-wrap items-center gap-2">
          Voici où en est votre argent aujourd&apos;hui.
          {verified ? <Badge tone="brand">Identité vérifiée · {user.kyc_level}</Badge> : <Badge tone="warning">Identité à vérifier</Badge>}
        </span>
      }
      action={<DemoBadge />}
    />
  );
}

function KycBanner() {
  return (
    <Link href="/kyc" className="mb-6 flex items-center gap-4 rounded-2xl border border-brand/30 bg-brand/10 p-4 transition hover:bg-brand/15">
      <IdCard className="h-6 w-6 shrink-0 text-brand" />
      <div className="flex-1">
        <p className="font-medium">Augmentez vos plafonds</p>
        <p className="text-sm text-muted">Vérifiez votre identité en 2 minutes pour envoyer davantage.</p>
      </div>
      <ChevronRight className="h-5 w-5 text-muted" />
    </Link>
  );
}

export default function DashboardPage() {
  return (
    <div className="mx-auto max-w-6xl">
      <Suspense fallback={<div className="mb-6 h-14 w-72 animate-pulse rounded-xl bg-surface-2" />}>
        <Greeting />
      </Suspense>

      <div className="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
        {/* min-w-0 : sans lui, le carrousel élargit la colonne au lieu de défiler */}
        <div className="min-w-0 space-y-6">
          <Card className="overflow-hidden">
            <p className="text-sm text-muted">Solde total estimé</p>
            <p className="mt-1 text-4xl font-semibold tracking-tight tabular-nums">{formatMoney(demoTotalInEur(), "EUR")}</p>
            <p className="mt-1 text-sm text-muted">Tous portefeuilles, convertis en EUR au taux du jour</p>

            <div className="-mx-1 mt-6 flex snap-x gap-4 overflow-x-auto px-1 pb-2">
              {DEMO_WALLETS.map((w) => (
                <div key={w.id} className={`${w.gradient} min-w-[230px] snap-start rounded-2xl p-5 text-white shadow-lg shadow-black/30`}>
                  <div className="flex items-center justify-between text-sm text-white/75">
                    <span>{w.label}</span>
                    <span className="font-mono">{w.masked}</span>
                  </div>
                  <p className="mt-6 text-2xl font-semibold tabular-nums">{formatMoney(w.available, w.currency)}</p>
                  <p className="mt-1 text-xs text-white/70">{w.reserved > 0 ? `${formatMoney(w.reserved, w.currency)} en cours d'envoi` : "Disponible"}</p>
                </div>
              ))}
            </div>
          </Card>

          <div className="grid grid-cols-3 gap-3 sm:grid-cols-5">
            {ACTIONS.map(({ href, label, icon: Icon, tone }) => (
              <Link key={label} href={href} className="group flex flex-col items-center gap-2 rounded-2xl border border-line bg-surface p-4 text-center transition hover:border-brand/40">
                <span className={`grid h-12 w-12 place-items-center rounded-2xl ${tone}`}>
                  <Icon className="h-5 w-5" />
                </span>
                <span className="text-xs font-medium sm:text-sm">{label}</span>
              </Link>
            ))}
          </div>

          <Card>
            <CardHeader
              title="Transactions récentes"
              action={<Link href="/transactions" className="text-sm font-medium text-brand hover:underline">Tout voir</Link>}
            />
            <div className="-mx-2 divide-y divide-line">
              {DEMO_TRANSFERS.slice(0, 4).map((t) => (
                <TransferRow key={t.id} transfer={t} />
              ))}
            </div>
          </Card>
        </div>

        <div className="min-w-0 space-y-6">
          <KycBanner />
          <Card>
            <CardHeader title="Paiement à venir" subtitle="Dans 10 jours" />
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Loyer · Douala</p>
                <p className="text-sm text-muted">Virement programmé</p>
              </div>
              <p className="text-lg font-semibold tabular-nums">{formatMoney(-35_820, "EUR")}</p>
            </div>
          </Card>
          <Card>
            <CardHeader title="Objectif d'épargne" subtitle="Vacances à Libreville" />
            <div className="flex items-baseline justify-between">
              <p className="text-2xl font-semibold tabular-nums">{formatMoney(98_000, "EUR")}</p>
              <p className="text-sm text-muted">sur {formatMoney(250_000, "EUR")}</p>
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface-3">
              <div className="h-full w-[39%] rounded-full bg-gradient-to-r from-brand to-violet" />
            </div>
          </Card>
          <Card>
            <CardHeader title="Partenaires de paiement" subtitle="Connectés en mode simulation" />
            <ul className="space-y-2 text-sm">
              {["MTN Mobile Money", "Airtel Money", "Visa Direct", "MoneyGram", "Western Union"].map((p) => (
                <li key={p} className="flex items-center justify-between">
                  <span>{p}</span>
                  <Badge tone="info">Simulation</Badge>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      </div>
    </div>
  );
}
