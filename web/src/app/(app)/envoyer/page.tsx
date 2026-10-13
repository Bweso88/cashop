import type { Metadata } from "next";
import { Suspense } from "react";
import { PageHeader } from "@/components/app/PageHeader";
import { SendWizard } from "@/components/send/SendWizard";
import { DemoBadge } from "@/components/ui/Badge";

export const metadata: Metadata = { title: "Envoyer de l'argent" };

export default function SendPage() {
  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader title="Envoyer de l'argent" subtitle="Comparez les partenaires et suivez votre transfert en temps réel." action={<DemoBadge />} />
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <SendWizard />
      </Suspense>
    </div>
  );
}
