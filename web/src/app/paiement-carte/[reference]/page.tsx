import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { CreditCard, Lock } from "lucide-react";
import { CardPaymentSimulator } from "@/components/card/CardPaymentSimulator";
import { Card } from "@/components/ui/Card";
import { ApiError } from "@/lib/api/client";
import { formatMoney } from "@/lib/money";
import { getCardPaymentSession, type CardPaymentSession } from "@/lib/transfers";

export const metadata: Metadata = { title: "Paiement par carte (simulation)" };

/**
 * Page de paiement du PRESTATAIRE, simulée pour la démonstration. En production, le client est
 * redirigé vers la page sécurisée du prestataire de carte : Cashop n'y a pas accès.
 */
async function Session({ params }: { params: Promise<{ reference: string }> }) {
  const { reference } = await params;
  let session: CardPaymentSession;
  try {
    session = await getCardPaymentSession(reference);
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 403)) notFound();
    throw error;
  }

  return (
    <Card className="space-y-6 p-6">
      <div className="flex items-center gap-3">
        <span className="grid h-11 w-11 place-items-center rounded-xl bg-info/15 text-info"><CreditCard className="h-5 w-5" /></span>
        <div>
          <p className="font-semibold">Prestataire de paiement par carte</p>
          <p className="text-sm text-muted">Paiement à {session.merchant}</p>
        </div>
      </div>
      <p className="rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm font-medium text-warning">{session.simulation_notice}</p>
      <div>
        <p className="text-sm text-muted">Montant</p>
        <p className="text-3xl font-semibold tabular-nums">{formatMoney(session.amount.amount, session.amount.currency)}</p>
        <p className="mt-1 text-sm text-muted">Carte de test •••• 4242 · aucune saisie de carte en démonstration</p>
      </div>
      {session.status === "AWAITING_CUSTOMER" ? (
        <CardPaymentSimulator reference={session.reference} transferId={session.transfer_id} />
      ) : (
        <p className="text-sm text-muted">Ce paiement est déjà conclu.</p>
      )}
      <p className="flex items-center gap-2 text-xs text-faint"><Lock className="h-3.5 w-3.5" /> Cashop ne reçoit jamais votre numéro de carte ni votre cryptogramme.</p>
    </Card>
  );
}

export default function CardPaymentPage({ params }: { params: Promise<{ reference: string }> }) {
  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-4 py-10">
      <Suspense fallback={<div className="h-96 animate-pulse rounded-3xl bg-surface" />}>
        <Session params={params} />
      </Suspense>
    </main>
  );
}
