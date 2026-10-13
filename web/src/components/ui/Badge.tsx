import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

type Tone = "brand" | "warning" | "danger" | "info" | "neutral";

const tones: Record<Tone, string> = {
  brand: "bg-brand/15 text-brand",
  warning: "bg-warning/15 text-warning",
  danger: "bg-danger/15 text-danger",
  info: "bg-info/15 text-info",
  neutral: "bg-surface-3 text-muted",
};

export function Badge({ tone = "neutral", children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
  return <span className={cn("inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium", tones[tone], className)}>{children}</span>;
}

/**
 * Signale les écrans alimentés par des données de démonstration, en attendant les endpoints
 * des phases 4 à 8 (wallet, ledger, transferts). Aucun montant affiché n'est réel.
 */
export function DemoBadge() {
  return (
    <Badge tone="warning" className="whitespace-nowrap">
      Données de démonstration
    </Badge>
  );
}
