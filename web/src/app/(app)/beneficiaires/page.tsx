import type { Metadata } from "next";
import { Suspense } from "react";
import { Send } from "lucide-react";
import { BeneficiaryManager } from "@/components/beneficiaries/BeneficiaryManager";
import { PageHeader } from "@/components/app/PageHeader";
import { ButtonLink } from "@/components/ui/Button";
import { getBeneficiaries, getCatalog } from "@/lib/transfers";

export const metadata: Metadata = { title: "Bénéficiaires" };

async function Manager() {
  const [beneficiaries, catalog] = await Promise.all([getBeneficiaries(), getCatalog()]);
  // Pays proposés par au moins un réseau (corridors au départ du Congo).
  const countries = [...new Map(catalog.networks.flatMap((n) => n.destinations).map((d) => [d.country, { code: d.country, name: d.name }])).values()];
  return <BeneficiaryManager beneficiaries={beneficiaries} countries={countries} />;
}

export default function BeneficiariesPage() {
  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        title="Bénéficiaires"
        subtitle="Les personnes à qui vous envoyez régulièrement. Leurs données sont chiffrées."
        action={<ButtonLink href="/envoyer"><Send className="h-4 w-4" /> Envoyer</ButtonLink>}
      />
      <Suspense fallback={<div className="h-64 animate-pulse rounded-3xl bg-surface" />}>
        <Manager />
      </Suspense>
    </div>
  );
}
