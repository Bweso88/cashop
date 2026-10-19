import type { Metadata } from "next";
import { Suspense } from "react";
import { PageHeader } from "@/components/app/PageHeader";
import { SendWizard } from "@/components/send/SendWizard";
import { SimulationBadge } from "@/components/ui/Badge";
import { getCurrentUser } from "@/lib/session";
import { getBeneficiaries, getCatalog } from "@/lib/transfers";

export const metadata: Metadata = { title: "Envoyer de l'argent" };

async function Wizard() {
  const [user, catalog, beneficiaries] = await Promise.all([getCurrentUser(), getCatalog(), getBeneficiaries()]);
  const phone = user.phone?.startsWith("+242") ? user.phone : "";

  return <SendWizard funding={catalog.funding} networks={catalog.networks} scenarios={catalog.scenarios} beneficiaries={beneficiaries} defaultMsisdn={phone} />;
}

export default function SendPage() {
  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader title="Envoyer de l'argent" subtitle="Payez par Mobile Money, le réseau de votre choix livre votre proche." action={<SimulationBadge />} />
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <Wizard />
      </Suspense>
    </div>
  );
}
