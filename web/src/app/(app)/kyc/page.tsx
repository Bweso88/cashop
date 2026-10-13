import type { Metadata } from "next";
import { PageHeader } from "@/components/app/PageHeader";
import { KycFlow } from "@/components/kyc/KycFlow";
import { DemoBadge } from "@/components/ui/Badge";

export const metadata: Metadata = { title: "Vérification d'identité" };

export default function KycPage() {
  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader title="Vérification d'identité" subtitle="Obligatoire pour envoyer de l'argent. Vos documents sont chiffrés." action={<DemoBadge />} />
      <KycFlow />
    </div>
  );
}
