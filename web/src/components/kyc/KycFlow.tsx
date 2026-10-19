"use client";

import { useState } from "react";
import { CheckCircle2, IdCard, RotateCcw, ScanFace, ShieldAlert } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { cn } from "@/lib/cn";

type Step = "document" | "selfie" | "review";

/**
 * Parcours KYC en DÉMONSTRATION : uniquement des documents synthétiques, aucune vraie pièce
 * d'identité ni photo n'est demandée ou lue. Une vérification officielle exige un prestataire
 * KYC autorisé (non choisi) : aucune vérification réelle n'est réalisée ici.
 */
export function KycFlow() {
  const [step, setStep] = useState<Step>("document");

  const steps: { key: Step; label: string; icon: typeof IdCard }[] = [
    { key: "document", label: "Pièce d'identité", icon: IdCard },
    { key: "selfie", label: "Selfie", icon: ScanFace },
    { key: "review", label: "Vérification", icon: CheckCircle2 },
  ];
  const index = steps.findIndex((s) => s.key === step);

  return (
    <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
      <Card className="h-fit">
        <ol className="space-y-1">
          {steps.map((s, i) => (
            <li key={s.key} className={cn("flex items-center gap-3 rounded-xl px-3 py-3 text-sm", i === index ? "bg-brand/10 font-medium text-brand" : i < index ? "text-text" : "text-faint")}>
              <s.icon className="h-5 w-5" />
              {s.label}
              {i < index && <CheckCircle2 className="ml-auto h-4 w-4 text-brand" />}
            </li>
          ))}
        </ol>
      </Card>

      <Card className="p-8">
        <div className="mb-6 flex gap-3 rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm">
          <ShieldAlert className="h-5 w-5 shrink-0 text-warning" />
          <p>Démonstration : n&apos;utilisez jamais une vraie pièce d&apos;identité. Seuls des documents synthétiques sont proposés, et aucune vérification officielle n&apos;est effectuée.</p>
        </div>

        {step === "document" && (
          <div className="text-center">
            <h2 className="text-xl font-semibold">Pièce d&apos;identité</h2>
            <p className="mt-1 text-muted">En production, la capture passera par un prestataire de vérification agréé.</p>
            <div className="mx-auto mt-8 flex aspect-[1.586] max-w-md flex-col justify-between rounded-2xl bg-gradient-to-br from-surface-3 to-surface-2 p-5 text-left ring-1 ring-line">
              <p className="text-xs font-semibold uppercase tracking-wider text-warning">Spécimen — document synthétique</p>
              <div className="flex items-end gap-4">
                <span className="grid h-20 w-16 place-items-center rounded-lg bg-surface text-faint"><ScanFace className="h-8 w-8" /></span>
                <div className="text-sm">
                  <p className="font-semibold">EXEMPLE Jean</p>
                  <p className="text-muted">Né le 01/01/1990</p>
                  <p className="font-mono text-muted">N° SPECIMEN-000000</p>
                </div>
              </div>
            </div>
            <Button variant="action" className="mt-8" onClick={() => setStep("selfie")}>Utiliser ce spécimen</Button>
          </div>
        )}

        {step === "selfie" && (
          <div className="text-center">
            <h2 className="text-xl font-semibold">Vérification du visage</h2>
            <p className="mt-1 text-muted">En démonstration, aucune caméra n&apos;est utilisée.</p>
            <span className="mx-auto mt-8 grid h-56 w-56 place-items-center rounded-full border-4 border-brand/40 bg-surface-2 ring-8 ring-brand/10">
              <ScanFace className="h-16 w-16 text-brand" />
            </span>
            <div className="mt-8 flex justify-center gap-3">
              <Button variant="ghost" onClick={() => setStep("document")}>Retour</Button>
              <Button variant="action" onClick={() => setStep("review")}>Simuler le selfie</Button>
            </div>
          </div>
        )}

        {step === "review" && (
          <div className="py-6 text-center">
            <span className="mx-auto grid h-24 w-24 place-items-center rounded-full bg-brand/10 ring-1 ring-brand/30">
              <CheckCircle2 className="h-14 w-14 text-brand" />
            </span>
            <h2 className="mt-6 text-xl font-semibold">Parcours simulé terminé</h2>
            <p className="mx-auto mt-2 max-w-sm text-muted">Aucune donnée n&apos;a été envoyée. Votre niveau de vérification est celui enregistré par Cashop.</p>
            <Button variant="ghost" className="mt-8" onClick={() => setStep("document")}>
              <RotateCcw className="h-4 w-4" /> Recommencer
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
