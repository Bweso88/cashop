import type { Metadata } from "next";
import { Suspense } from "react";
import QRCode from "qrcode";
import { Banknote, Smartphone } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { Badge, DemoBadge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { getCurrentUser } from "@/lib/session";

export const metadata: Metadata = { title: "Recevoir de l'argent" };

async function ReceiveCard() {
  const user = await getCurrentUser();
  // Lien de paiement Cashop (format à définir avec l'endpoint "receive" des phases suivantes).
  const link = `cashop://pay/${user.id}`;
  const svg = await QRCode.toString(link, { type: "svg", margin: 1, color: { dark: "#0a0d14", light: "#ffffff" } });

  return (
    <Card className="flex flex-col items-center p-8 text-center">
      <div className="w-56 rounded-2xl bg-white p-3" dangerouslySetInnerHTML={{ __html: svg }} />
      <p className="mt-5 text-lg font-semibold">{[user.first_name, user.last_name].filter(Boolean).join(" ")}</p>
      <p className="text-sm text-muted">{user.phone}</p>
      <p className="mt-4 max-w-xs text-sm text-muted">Faites scanner ce code par un autre utilisateur Cashop pour recevoir de l&apos;argent instantanément.</p>
    </Card>
  );
}

export default function ReceivePage() {
  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader title="Recevoir de l'argent" subtitle="Partagez votre code ou rechargez votre portefeuille." action={<DemoBadge />} />
      <div className="grid gap-6 lg:grid-cols-2">
        <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
          <ReceiveCard />
        </Suspense>
        <div className="space-y-4">
          <Card>
            <CardHeader title="Recharger depuis Mobile Money" subtitle="Validation sur votre téléphone (collecte)" action={<Badge tone="info">Bientôt</Badge>} />
            <div className="flex items-center gap-3 text-sm text-muted">
              <Smartphone className="h-5 w-5 text-brand" /> MTN Mobile Money, Airtel Money — selon les pays confirmés par les opérateurs.
            </div>
          </Card>
          <Card>
            <CardHeader title="Recevoir d'un proche à l'étranger" subtitle="Votre proche envoie via Cashop, MoneyGram ou Western Union" />
            <div className="flex items-center gap-3 text-sm text-muted">
              <Banknote className="h-5 w-5 text-brand" /> Communiquez votre nom exact et votre numéro de téléphone.
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
