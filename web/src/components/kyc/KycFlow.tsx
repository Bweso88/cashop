"use client";

import { useState } from "react";
import { Camera, CheckCircle2, IdCard, ScanFace, Upload } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { cn } from "@/lib/cn";

type Step = "document" | "selfie" | "review";

/**
 * Parcours KYC (maquette fonctionnelle). L'envoi réel des pièces passera par
 * POST /api/v1/kyc/documents ; la vérification (OCR, vivant, comparaison faciale)
 * dépend du prestataire KYC à choisir.
 */
export function KycFlow() {
  const [step, setStep] = useState<Step>("document");
  const [documentPreview, setDocumentPreview] = useState<string | null>(null);
  const [selfiePreview, setSelfiePreview] = useState<string | null>(null);

  function pick(setter: (v: string) => void) {
    return (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (file) setter(URL.createObjectURL(file));
    };
  }

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
        {step === "document" && (
          <div className="text-center">
            <h2 className="text-xl font-semibold">Scannez votre pièce d&apos;identité</h2>
            <p className="mt-1 text-muted">Placez la carte dans le cadre, bien éclairée et sans reflet.</p>
            <label className="relative mx-auto mt-8 flex aspect-[1.586] max-w-md cursor-pointer items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-brand/50 bg-surface-2">
              {documentPreview ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={documentPreview} alt="Aperçu de la pièce" className="h-full w-full object-cover" />
              ) : (
                <span className="flex flex-col items-center gap-3 text-muted">
                  <Camera className="h-10 w-10 text-brand" />
                  Prendre une photo ou importer un fichier
                </span>
              )}
              <span className="pointer-events-none absolute inset-4 rounded-xl border-2 border-brand/70 [mask:linear-gradient(#000_0_0)_content-box,linear-gradient(#000_0_0)]" aria-hidden />
              <input type="file" accept="image/*" capture="environment" className="sr-only" onChange={pick(setDocumentPreview)} />
            </label>
            <div className="mt-8 flex justify-center gap-3">
              <Button variant="action" disabled={!documentPreview} onClick={() => setStep("selfie")}>Continuer</Button>
            </div>
          </div>
        )}

        {step === "selfie" && (
          <div className="text-center">
            <h2 className="text-xl font-semibold">Vérifions que c&apos;est bien vous</h2>
            <p className="mt-1 text-muted">Un selfie de face, sans lunettes ni chapeau.</p>
            <label className="mx-auto mt-8 grid h-56 w-56 cursor-pointer place-items-center overflow-hidden rounded-full border-4 border-brand/40 bg-surface-2 ring-8 ring-brand/10">
              {selfiePreview ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={selfiePreview} alt="Aperçu du selfie" className="h-full w-full object-cover" />
              ) : (
                <ScanFace className="h-16 w-16 text-brand" />
              )}
              <input type="file" accept="image/*" capture="user" className="sr-only" onChange={pick(setSelfiePreview)} />
            </label>
            <div className="mt-8 flex justify-center gap-3">
              <Button variant="ghost" onClick={() => setStep("document")}>Retour</Button>
              <Button variant="action" disabled={!selfiePreview} onClick={() => setStep("review")}>M&apos;identifier</Button>
            </div>
          </div>
        )}

        {step === "review" && (
          <div className="py-6 text-center">
            <span className="mx-auto grid h-24 w-24 place-items-center rounded-full bg-brand/10 ring-1 ring-brand/30">
              <CheckCircle2 className="h-14 w-14 text-brand" />
            </span>
            <h2 className="mt-6 text-xl font-semibold">Documents reçus</h2>
            <p className="mx-auto mt-2 max-w-sm text-muted">Votre identité est en cours de vérification. Vous serez notifié dès qu&apos;elle sera validée.</p>
            <p className="mt-4 text-xs text-warning">Démonstration : les images restent dans votre navigateur, rien n&apos;est envoyé.</p>
            <Button variant="ghost" className="mt-8" onClick={() => { setStep("document"); setDocumentPreview(null); setSelfiePreview(null); }}>
              <Upload className="h-4 w-4" /> Recommencer
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
