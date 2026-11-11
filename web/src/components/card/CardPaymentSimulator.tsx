"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { CheckCircle2, Loader2, ShieldX, XCircle } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/Field";
import { completeCardPaymentAction } from "@/lib/transfer-actions";

const OUTCOMES = [
  { code: "APPROVED", label: "Paiement accepté", icon: CheckCircle2, variant: "primary" },
  { code: "DECLINED", label: "Refusé par la banque", icon: XCircle, variant: "ghost" },
  { code: "AUTHENTICATION_FAILED", label: "Échec 3-D Secure", icon: ShieldX, variant: "ghost" },
] as const;

/** Choix du résultat sur la page de paiement simulée : aucune donnée de carte n'est saisie. */
export function CardPaymentSimulator({ reference, transferId }: { reference: string; transferId: string }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string>();

  function choose(outcome: (typeof OUTCOMES)[number]["code"]) {
    startTransition(async () => {
      const res = await completeCardPaymentAction(reference, outcome);
      if (res.ok) router.push(`/transactions/${res.data.transfer_id}`);
      else setError(res.error);
    });
  }

  return (
    <div className="space-y-3">
      {OUTCOMES.map(({ code, label, icon: Icon, variant }) => (
        <Button key={code} variant={variant} className="w-full" disabled={pending} onClick={() => choose(code)}>
          {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Icon className="h-4 w-4" />} {label}
        </Button>
      ))}
      <FormError message={error} />
      <button type="button" onClick={() => router.push(`/transactions/${transferId}`)} className="w-full pt-2 text-center text-sm text-muted hover:text-text">
        Annuler et revenir au suivi
      </button>
    </div>
  );
}
