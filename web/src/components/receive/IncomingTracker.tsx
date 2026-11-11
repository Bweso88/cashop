"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, useTransition } from "react";
import { Loader2, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { refreshIncomingAction } from "@/lib/transfer-actions";

const POLL_MS = 5_000;
const MAX_POLLS = 24;

/** Relit le statut du versement chez le réseau, automatiquement puis à la demande. */
export function IncomingTracker({ id, status }: { id: string; status: string }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [polls, setPolls] = useState(0);
  const [error, setError] = useState<string>();

  function refresh() {
    startTransition(async () => {
      const res = await refreshIncomingAction(id);
      setPolls((n) => n + 1);
      if (res.ok) {
        setError(undefined);
        router.refresh();
      } else setError(res.error);
    });
  }

  useEffect(() => {
    if (polls >= MAX_POLLS) return;
    const timer = setTimeout(refresh, POLL_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- refresh est stable pour cet id
  }, [polls, status]);

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Button variant="ghost" onClick={refresh} disabled={pending}>
        {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />} Actualiser
      </Button>
      {polls < MAX_POLLS && <span className="text-xs text-muted">Mise à jour automatique…</span>}
      {error && <span className="text-xs text-danger">{error}</span>}
    </div>
  );
}
