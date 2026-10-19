"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, useTransition } from "react";
import { Loader2, RefreshCw, Smartphone } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { refreshTransferAction } from "@/lib/transfer-actions";
import type { TransferStatus } from "@/lib/transfers";

/** Statuts qui avancent seuls (le prestataire n'a pas encore répondu) : suivi automatique. */
const MOVING: TransferStatus[] = ["PAYMENT_PENDING", "PAYMENT_CONFIRMED", "TRANSFER_PENDING", "TRANSFER_CONFIRMED", "PAYOUT_PENDING", "REFUND_PENDING", "REQUIRES_RECONCILIATION"];
const POLL_MS = 5_000;
const MAX_POLLS = 24; // 2 minutes, puis bouton manuel

/**
 * Relit le statut chez les prestataires (POST /refresh) puis recharge la page serveur.
 * Le minuteur ne fait que déclencher l'appel : aucun état n'est modifié dans l'effet.
 */
export function TransferTracker({ id, status }: { id: string; status: TransferStatus }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [polls, setPolls] = useState(0);
  const [error, setError] = useState<string>();
  const moving = MOVING.includes(status);

  function refresh() {
    startTransition(async () => {
      const res = await refreshTransferAction(id);
      setPolls((n) => n + 1);
      if (res.ok) {
        setError(undefined);
        router.refresh();
      } else setError(res.error);
    });
  }

  useEffect(() => {
    if (!moving || polls >= MAX_POLLS) return;
    const timer = setTimeout(refresh, POLL_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- refresh est stable pour cet id
  }, [moving, polls, status]);

  return (
    <div className="space-y-3">
      {status === "PAYMENT_PENDING" && (
        <div className="flex gap-3 rounded-2xl border border-info/30 bg-info/10 p-4 text-sm">
          <Smartphone className="h-5 w-5 shrink-0 text-info" />
          <p>Validez le paiement sur votre téléphone, auprès de votre opérateur, dans les 5 minutes. Cashop ne vous demandera jamais votre code Mobile Money.</p>
        </div>
      )}
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" onClick={refresh} disabled={pending}>
          {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />} Actualiser
        </Button>
        {moving && polls < MAX_POLLS && <span className="text-xs text-muted">Mise à jour automatique…</span>}
        {error && <span className="text-xs text-danger">{error}</span>}
      </div>
    </div>
  );
}
