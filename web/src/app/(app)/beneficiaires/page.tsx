import type { Metadata } from "next";
import { Plus } from "lucide-react";
import { PageHeader } from "@/components/app/PageHeader";
import { Badge, DemoBadge } from "@/components/ui/Badge";
import { ButtonLink } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { countryFlag, countryName, DEMO_BENEFICIARIES, PAYOUT_LABELS, PROVIDER_LABELS } from "@/lib/demo-data";

export const metadata: Metadata = { title: "Bénéficiaires" };

export default function BeneficiariesPage() {
  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        title="Bénéficiaires"
        subtitle="Les personnes à qui vous envoyez régulièrement."
        action={
          <div className="flex items-center gap-3">
            <DemoBadge />
            <ButtonLink href="/envoyer"><Plus className="h-4 w-4" /> Nouveau transfert</ButtonLink>
          </div>
        }
      />
      <div className="grid gap-4 sm:grid-cols-2">
        {DEMO_BENEFICIARIES.map((b) => (
          <Card key={b.id} className="flex items-center gap-4">
            <span className="grid h-12 w-12 place-items-center rounded-full bg-gradient-to-br from-brand/30 to-violet/30 font-semibold">{b.name.split(" ").map((p) => p[0]).join("")}</span>
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium">{b.name}</p>
              <p className="truncate text-sm text-muted">{countryFlag(b.country)} {countryName(b.country)} · {b.masked}</p>
              <div className="mt-2 flex gap-2">
                <Badge>{PAYOUT_LABELS[b.payoutMethod]}</Badge>
                {b.provider && <Badge tone="info">{PROVIDER_LABELS[b.provider]}</Badge>}
              </div>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
