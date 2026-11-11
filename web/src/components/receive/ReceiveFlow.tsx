"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { Copy, Gift, Loader2, Lock, Search, Smartphone } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { Field, FormError, Input, Select } from "@/components/ui/Field";
import { cn } from "@/lib/cn";
import { countryFlag, countryName } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { claimIncomingAction, lookupIncomingAction, simulateIncomingAction } from "@/lib/transfer-actions";
import type { FundingProvider, IncomingLookup, Named } from "@/lib/transfers";

const DEMO_SCENARIOS = [
  { code: "SUCCESS", label: "Versement réussi" },
  { code: "PAYOUT_FAILED", label: "Versement refusé par le réseau" },
  { code: "PAYOUT_PENDING", label: "Versement en attente prolongée" },
];

/**
 * Recevoir un transfert : référence communiquée par l'expéditeur → vérification auprès du réseau →
 * versement par le réseau sur votre MTN ou Airtel. Cashop ne reçoit pas l'argent.
 */
export function ReceiveFlow({ networks, wallets, simulation, defaultMsisdn }: { networks: Named[]; wallets: FundingProvider[]; simulation: boolean; defaultMsisdn: string }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [network, setNetwork] = useState(networks[0]?.code ?? "");
  const [reference, setReference] = useState("");
  const [found, setFound] = useState<IncomingLookup | null>(null);
  const [wallet, setWallet] = useState(wallets.find((w) => w.code === "mtn_momo")?.code ?? wallets[0]?.code ?? "");
  const [msisdn, setMsisdn] = useState(defaultMsisdn);
  const [pin, setPin] = useState("");
  const [idempotencyKey, setIdempotencyKey] = useState("");
  const [error, setError] = useState<string>();
  // Démonstration
  const [demoAmount, setDemoAmount] = useState("75000");
  const [demoScenario, setDemoScenario] = useState("SUCCESS");
  const [demoReference, setDemoReference] = useState<string>();

  const msisdnError = /^\+242\d{9}$/.test(msisdn.replace(/\s/g, "")) ? undefined : "Numéro congolais attendu : +242 suivi de 9 chiffres";

  function simulate() {
    setError(undefined);
    startTransition(async () => {
      const res = await simulateIncomingAction({ network, amount: Number(demoAmount) || 0, scenario: demoScenario });
      if (!res.ok) return setError(res.error);
      setDemoReference(res.data.reference);
      setReference(res.data.reference);
      setFound(null);
    });
  }

  function lookup() {
    setError(undefined);
    startTransition(async () => {
      const res = await lookupIncomingAction({ network, reference: reference.trim() });
      if (!res.ok) return setError(res.error);
      if (res.data.incoming_transfer_id) return router.push(`/recevoir/${res.data.incoming_transfer_id}`);
      setFound(res.data);
      setIdempotencyKey(crypto.randomUUID()); // une clé par transfert trouvé
    });
  }

  function claim() {
    if (!found) return;
    setError(undefined);
    startTransition(async () => {
      const res = await claimIncomingAction({ idempotency_key: idempotencyKey, network: found.network.code, reference: found.reference, payout_provider: wallet, payout_msisdn: msisdn.replace(/\s/g, ""), pin });
      if (res.ok) return router.push(`/recevoir/${res.data.id}`);
      setPin("");
      setError(Object.values(res.fieldErrors ?? {})[0] || res.error);
    });
  }

  return (
    <div className="space-y-6">
      {simulation && (
        <Card className="border-warning/40">
          <CardHeader title="Démonstration : un proche vous envoie de l'argent" subtitle="Crée un transfert entrant fictif à votre nom, pour tester la réception." action={<Gift className="h-5 w-5 text-warning" />} />
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Montant (XAF)"><Input value={demoAmount} onChange={(e) => setDemoAmount(e.target.value.replace(/\D/g, ""))} inputMode="numeric" /></Field>
            <Field label="Scénario">
              <Select value={demoScenario} onChange={(e) => setDemoScenario(e.target.value)}>
                {DEMO_SCENARIOS.map((s) => <option key={s.code} value={s.code}>{s.label}</option>)}
              </Select>
            </Field>
            <div className="flex items-end"><Button variant="ghost" className="w-full" disabled={pending} onClick={simulate}>Simuler l&apos;envoi</Button></div>
          </div>
          {demoReference && (
            <p className="mt-4 flex flex-wrap items-center gap-2 text-sm">
              Référence reçue : <span className="font-mono font-semibold">{demoReference}</span>
              <button type="button" onClick={() => navigator.clipboard?.writeText(demoReference)} className="inline-flex items-center gap-1 text-brand"><Copy className="h-3.5 w-3.5" /> Copier</button>
              <span className="text-muted">(déjà saisie ci-dessous)</span>
            </p>
          )}
        </Card>
      )}

      <Card className="p-6">
        <CardHeader title="Recevoir un transfert" subtitle="Avec la référence communiquée par l'expéditeur" />
        {!found ? (
          <div className="space-y-4">
            <Field label="Réseau utilisé par l'expéditeur">
              <Select value={network} onChange={(e) => setNetwork(e.target.value)}>
                {networks.map((n) => <option key={n.code} value={n.code}>{n.name}</option>)}
              </Select>
            </Field>
            <Field label="Référence du transfert">
              <Input value={reference} onChange={(e) => setReference(e.target.value)} placeholder="Ex. SIM-IN-MG-XXXXXXXX" autoCapitalize="characters" className="font-mono" />
            </Field>
            <FormError message={error} />
            <Button className="w-full" disabled={reference.trim().length < 6 || pending} onClick={lookup}>
              {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />} Rechercher le transfert
            </Button>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="rounded-2xl border border-brand/30 bg-brand/10 p-4">
              <p className="text-sm text-muted">Transfert {found.network.name} pour vous</p>
              <p className="mt-1 text-3xl font-semibold tabular-nums">{formatMoney(found.amount.amount, found.amount.currency)}</p>
              <p className="mt-1 text-sm text-muted">De {found.sender.name} · {countryFlag(found.sender.country)} {countryName(found.sender.country)}</p>
              <p className="mt-1 font-mono text-xs text-muted">{found.reference}</p>
            </div>
            <div>
              <p className="mb-2 text-sm font-medium text-muted">Recevoir sur</p>
              <div className="grid gap-3 sm:grid-cols-2">
                {wallets.map((w) => (
                  <button key={w.code} type="button" onClick={() => setWallet(w.code)} className={cn("flex items-center gap-3 rounded-xl border p-4 text-left text-sm transition", wallet === w.code ? "border-brand bg-brand/10" : "border-line bg-surface-2 hover:border-faint")}>
                    <Smartphone className={cn("h-5 w-5", wallet === w.code ? "text-brand" : "text-muted")} /> {w.name}
                  </button>
                ))}
              </div>
            </div>
            <Field label="Numéro à créditer" error={msisdn ? msisdnError : undefined} hint="Le réseau verse directement sur ce compte : Cashop ne garde jamais l'argent.">
              <Input value={msisdn} onChange={(e) => setMsisdn(e.target.value)} inputMode="tel" placeholder="+242 06 000 0000" />
            </Field>
            <Field label="Confirmez avec votre PIN Cashop">
              <div className="relative">
                <Lock className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <Input value={pin} onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, 6))} type="password" inputMode="numeric" autoComplete="off" placeholder="••••••" className="pl-10 tracking-[0.5em]" aria-label="PIN Cashop" />
              </div>
            </Field>
            <FormError message={error} />
            <div className="flex flex-col gap-3 sm:flex-row">
              <Button variant="ghost" onClick={() => { setFound(null); setError(undefined); }}>Autre référence</Button>
              <Button className="flex-1" disabled={pin.length !== 6 || !!msisdnError || pending} onClick={claim}>
                {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Recevoir {formatMoney(found.amount.amount, found.amount.currency)}
              </Button>
            </div>
            {found.simulation_notice && <p className="text-xs text-warning">{found.simulation_notice}</p>}
          </div>
        )}
      </Card>
    </div>
  );
}
