import type { Metadata } from "next";
import { Banknote, Search, Smartphone } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { Badge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";

export const metadata: Metadata = { title: "Recevoir de l'argent" };

/**
 * Parcours B (réception) : pas encore développé. Cashop ne détient pas de fonds : un transfert reçu
 * est versé par le réseau (agence ou Mobile Money), jamais sur un solde Cashop.
 */
export default function ReceivePage() {
  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader title="Recevoir de l'argent" subtitle="Un proche vous envoie de l'argent par Western Union, MoneyGram ou Ria." />
      <div className="grid gap-6 md:grid-cols-2">
        <Card>
          <CardHeader title="Suivre un transfert reçu" subtitle="Avec la référence que vous a communiquée l'expéditeur" action={<Badge tone="info">Bientôt</Badge>} />
          <div className="flex items-center gap-3 text-sm text-muted">
            <Search className="h-5 w-5 shrink-0 text-brand" /> La consultation dépend des API de chaque réseau (documentation partenaire requise).
          </div>
        </Card>
        <Card>
          <CardHeader title="Retrait en agence" subtitle="Avec une pièce d'identité à votre nom exact" />
          <div className="flex items-center gap-3 text-sm text-muted">
            <Banknote className="h-5 w-5 shrink-0 text-brand" /> Présentez la référence du transfert dans une agence du réseau choisi par l&apos;expéditeur.
          </div>
        </Card>
        <Card>
          <CardHeader title="Versement sur Mobile Money" subtitle="Selon les corridors ouverts par chaque réseau" action={<Badge tone="info">Bientôt</Badge>} />
          <div className="flex items-center gap-3 text-sm text-muted">
            <Smartphone className="h-5 w-5 shrink-0 text-brand" /> Communiquez à l&apos;expéditeur votre numéro MTN ou Airtel avec l&apos;indicatif +242.
          </div>
        </Card>
      </div>
    </div>
  );
}
