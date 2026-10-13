import type { Metadata } from "next";
import { Suspense } from "react";
import { PageHeader } from "@/components/app/PageHeader";
import { Badge } from "@/components/ui/Badge";
import { Card, CardHeader } from "@/components/ui/Card";
import { getCurrentUser } from "@/lib/session";

export const metadata: Metadata = { title: "Profil" };

async function ProfileDetails() {
  const user = await getCurrentUser();
  const rows: [string, React.ReactNode][] = [
    ["Prénom", user.first_name ?? "—"],
    ["Nom", user.last_name ?? "—"],
    ["E-mail", <span key="e" className="inline-flex items-center gap-2">{user.email}{user.email_verified ? <Badge tone="brand">Vérifié</Badge> : <Badge tone="warning">Non vérifié</Badge>}</span>],
    ["Téléphone", <span key="p" className="inline-flex items-center gap-2">{user.phone ?? "—"}{user.phone_verified && <Badge tone="brand">Vérifié</Badge>}</span>],
    ["Pays de résidence", user.country ?? "—"],
    ["Niveau KYC", `${user.kyc_level ?? "—"} · ${user.kyc_status ?? ""}`],
  ];

  return (
    <Card className="p-6">
      <CardHeader title="Informations personnelles" subtitle="Données réelles issues de l'API Cashop" />
      <dl className="divide-y divide-line text-sm">
        {rows.map(([k, v]) => (
          <div key={k} className="flex justify-between gap-4 py-3">
            <dt className="text-muted">{k}</dt>
            <dd className="text-right font-medium">{v}</dd>
          </div>
        ))}
      </dl>
    </Card>
  );
}

export default function ProfilePage() {
  return (
    <div className="mx-auto max-w-3xl">
      <PageHeader title="Profil" />
      <Suspense fallback={<div className="h-80 animate-pulse rounded-3xl bg-surface" />}>
        <ProfileDetails />
      </Suspense>
    </div>
  );
}
