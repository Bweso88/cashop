import type { Metadata } from "next";
import { Suspense } from "react";
import { Fingerprint, KeyRound, ShieldCheck } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { MfaSetup } from "@/components/security/MfaSetup";
import { PinForm } from "@/components/security/PinForm";
import { Badge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { getCurrentUser } from "@/lib/session";

export const metadata: Metadata = { title: "Sécurité" };

async function SecuritySettings() {
  const user = await getCurrentUser();

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Card className="p-6">
        <CardHeader
          title="PIN de transaction"
          subtitle="Demandé pour confirmer chaque envoi."
          action={user.pin_set ? <Badge tone="brand"><KeyRound className="h-3 w-3" /> Défini</Badge> : <Badge tone="warning">À définir</Badge>}
        />
        <PinForm pinSet={!!user.pin_set} />
      </Card>
      <Card className="p-6">
        <CardHeader
          title="Double authentification"
          subtitle="Un code temporaire en plus du mot de passe."
          action={user.mfa_enabled ? <Badge tone="brand"><ShieldCheck className="h-3 w-3" /> Activée</Badge> : <Badge tone="warning">Désactivée</Badge>}
        />
        <MfaSetup enabled={!!user.mfa_enabled} />
      </Card>
      <Card className="p-6 lg:col-span-2">
        <CardHeader title="Biométrie" subtitle="Face ID / empreinte digitale, depuis l'application mobile." action={<Fingerprint className="h-5 w-5 text-brand" />} />
        <p className="text-sm text-muted">La clé biométrique est créée dans la puce sécurisée de votre téléphone. Elle ne quitte jamais l&apos;appareil : Cashop ne reçoit qu&apos;une signature.</p>
      </Card>
    </div>
  );
}

export default function SecurityPage() {
  return (
    <div className="mx-auto max-w-5xl">
      <PageHeader title="Sécurité" subtitle="Ces réglages sont enregistrés sur votre compte (API réelle)." />
      <Suspense fallback={<div className="h-80 animate-pulse rounded-3xl bg-surface" />}>
        <SecuritySettings />
      </Suspense>
    </div>
  );
}
