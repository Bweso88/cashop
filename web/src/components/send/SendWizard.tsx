"use client";

import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { Banknote, Check, CheckCircle2, ChevronLeft, CreditCard, Loader2, Lock, Smartphone } from "lucide-react";
import { Button, ButtonLink } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Field, Input, Select } from "@/components/ui/Field";
import { Badge } from "@/components/ui/Badge";
import { cn } from "@/lib/cn";
import {
  availableMethods, COUNTRIES, countryFlag, DEMO_BENEFICIARIES, DEMO_WALLETS, demoQuotes,
  PAYOUT_LABELS, PROVIDER_LABELS, type DemoQuote, type PayoutMethod,
} from "@/lib/demo-data";
import { formatMoney, parseAmount } from "@/lib/money";

const STEPS = ["Montant", "Offres", "Bénéficiaire", "Confirmation"] as const;
const METHOD_ICON: Record<PayoutMethod, typeof Smartphone> = { mobile_money: Smartphone, cash_pickup: Banknote, card: CreditCard, bank_account: CreditCard };

type Recipient = { name: string; detail: string };

export function SendWizard() {
  const params = useSearchParams();
  const wallet = DEMO_WALLETS.find((w) => w.currency === "EUR")!;

  // Pré-sélection depuis les raccourcis de l'accueil (?mode=cash_pickup…), lue une seule fois.
  const [initial] = useState(() => {
    const mode = params.get("mode") as PayoutMethod | null;
    const target = mode ? COUNTRIES.find((c) => availableMethods(c.code).includes(mode)) : undefined;
    return target && mode ? { country: target.code, method: mode } : { country: "CM", method: "mobile_money" as PayoutMethod };
  });

  const [step, setStep] = useState(0);
  const [amountInput, setAmountInput] = useState("100");
  const [country, setCountry] = useState(initial.country);
  const [method, setMethod] = useState<PayoutMethod>(initial.method);
  const [quote, setQuote] = useState<DemoQuote | null>(null);
  const [recipient, setRecipient] = useState<Recipient | null>(null);
  const [pin, setPin] = useState("");
  const [phase, setPhase] = useState<"form" | "processing" | "done">("form");

  const methods = availableMethods(country);
  const effectiveMethod = methods.includes(method) ? method : methods[0];
  const amount = parseAmount(amountInput, "EUR");
  const amountError = amount === null ? "Montant invalide" : amount < 1_000 ? "Minimum 10,00 €" : amount > wallet.available ? "Solde insuffisant" : undefined;
  const quotes = amount && effectiveMethod ? demoQuotes(amount, country, effectiveMethod) : [];

  function submit() {
    setPhase("processing");
    setTimeout(() => setPhase("done"), 2200);
  }

  if (phase !== "form" && quote && recipient) {
    return <Outcome phase={phase} quote={quote} recipient={recipient} />;
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
      <Card className="p-6">
        <ol className="mb-8 flex items-center gap-2" aria-label="Étapes">
          {STEPS.map((label, i) => (
            <li key={label} className="flex flex-1 items-center gap-2">
              <span className={cn("grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold", i < step ? "bg-brand text-ink" : i === step ? "bg-brand/20 text-brand ring-1 ring-brand" : "bg-surface-3 text-faint")}>
                {i < step ? <Check className="h-4 w-4" /> : i + 1}
              </span>
              <span className={cn("hidden text-sm sm:block", i === step ? "font-medium" : "text-muted")}>{label}</span>
              {i < STEPS.length - 1 && <span className="mx-1 h-px flex-1 bg-line" />}
            </li>
          ))}
        </ol>

        {step > 0 && (
          <button onClick={() => setStep(step - 1)} className="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-text">
            <ChevronLeft className="h-4 w-4" /> Retour
          </button>
        )}

        {step === 0 && (
          <div className="space-y-6">
            <Field label="Vous envoyez" error={amountInput ? amountError : undefined} hint={`Disponible : ${formatMoney(wallet.available, "EUR")}`}>
              <div className="relative">
                <Input value={amountInput} onChange={(e) => setAmountInput(e.target.value)} inputMode="decimal" className="pr-16 text-2xl font-semibold" aria-label="Montant en euros" />
                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted">EUR</span>
              </div>
            </Field>
            <Field label="Pays de destination">
              <Select value={country} onChange={(e) => setCountry(e.target.value)}>
                {COUNTRIES.map((c) => (
                  <option key={c.code} value={c.code}>{c.flag} {c.name} · {c.currency}</option>
                ))}
              </Select>
            </Field>
            <div>
              <p className="mb-2 text-sm font-medium text-muted">Mode de réception</p>
              <div className="grid gap-3 sm:grid-cols-3">
                {methods.map((m) => {
                  const Icon = METHOD_ICON[m];
                  return (
                    <button key={m} type="button" onClick={() => setMethod(m)} className={cn("flex items-center gap-3 rounded-xl border p-4 text-left text-sm transition", effectiveMethod === m ? "border-brand bg-brand/10" : "border-line bg-surface-2 hover:border-faint")}>
                      <Icon className={cn("h-5 w-5", effectiveMethod === m ? "text-brand" : "text-muted")} />
                      {PAYOUT_LABELS[m]}
                    </button>
                  );
                })}
              </div>
            </div>
            <Button className="w-full" disabled={!!amountError || quotes.length === 0} onClick={() => setStep(1)}>
              Comparer les offres
            </Button>
          </div>
        )}

        {step === 1 && (
          <div className="space-y-3">
            <p className="text-sm text-muted">{quotes.length} offre{quotes.length > 1 ? "s" : ""} pour {formatMoney(amount!, "EUR")} vers {countryFlag(country)} {COUNTRIES.find((c) => c.code === country)?.name}.</p>
            {quotes.map((q, i) => (
              <button key={q.id} type="button" onClick={() => { setQuote(q); setStep(2); }} className="flex w-full items-center gap-4 rounded-2xl border border-line bg-surface-2 p-4 text-left transition hover:border-brand/50">
                <div className="flex-1">
                  <p className="flex items-center gap-2 font-semibold">
                    {PROVIDER_LABELS[q.provider]}
                    {i === 0 && <Badge tone="brand">Moins cher</Badge>}
                  </p>
                  <p className="mt-1 text-sm text-muted">Frais {formatMoney(q.fees, "EUR")} · {q.delay}</p>
                </div>
                <div className="text-right">
                  <p className="text-xs text-muted">Le bénéficiaire reçoit</p>
                  <p className="text-lg font-semibold tabular-nums text-brand">{formatMoney(q.receiveAmount, q.receiveCurrency)}</p>
                </div>
              </button>
            ))}
          </div>
        )}

        {step === 2 && quote && <RecipientStep country={country} method={quote.method} onSelect={(r) => { setRecipient(r); setStep(3); }} />}

        {step === 3 && quote && recipient && (
          <div className="space-y-6">
            <dl className="divide-y divide-line rounded-2xl border border-line bg-surface-2 text-sm">
              {[
                ["Bénéficiaire", `${recipient.name} · ${recipient.detail}`],
                ["Via", `${PROVIDER_LABELS[quote.provider]} · ${PAYOUT_LABELS[quote.method]}`],
                ["Montant envoyé", formatMoney(quote.sendAmount, "EUR")],
                ["Frais", formatMoney(quote.fees, "EUR")],
                ["Taux appliqué", `1 EUR = ${quote.fxRate.toLocaleString("fr-FR")} ${quote.receiveCurrency}`],
                ["Total débité", formatMoney(quote.totalDebit, "EUR")],
              ].map(([k, v]) => (
                <div key={k} className="flex justify-between gap-4 px-4 py-3">
                  <dt className="text-muted">{k}</dt>
                  <dd className="text-right font-medium">{v}</dd>
                </div>
              ))}
              <div className="flex justify-between gap-4 px-4 py-4">
                <dt className="font-medium">Le bénéficiaire reçoit</dt>
                <dd className="text-lg font-semibold text-brand">{formatMoney(quote.receiveAmount, quote.receiveCurrency)}</dd>
              </div>
            </dl>
            <Field label="Confirmez avec votre PIN de transaction" hint="Sur mobile, la biométrie peut remplacer le PIN.">
              <div className="relative">
                <Lock className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <Input value={pin} onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, 6))} type="password" inputMode="numeric" placeholder="••••••" className="pl-10 tracking-[0.5em]" aria-label="PIN" />
              </div>
            </Field>
            <Button className="w-full" disabled={pin.length !== 6} onClick={submit}>
              Envoyer {formatMoney(quote.totalDebit, "EUR")}
            </Button>
          </div>
        )}
      </Card>

      <aside className="space-y-4">
        <Card>
          <p className="text-sm text-muted">Débité depuis</p>
          <p className="mt-1 font-semibold">{wallet.label}</p>
          <p className="text-sm text-muted">{formatMoney(wallet.available, "EUR")} disponibles</p>
        </Card>
        <Card className="text-sm text-muted">
          <p className="mb-2 font-medium text-text">Bon à savoir</p>
          Les montants, frais et taux affichés ici sont <strong className="text-warning">simulés</strong>. Les vrais tarifs viendront des API de chaque partenaire, une fois leur intégration certifiée.
        </Card>
      </aside>
    </div>
  );
}

function RecipientStep({ country, method, onSelect }: { country: string; method: PayoutMethod; onSelect: (r: Recipient) => void }) {
  const saved = DEMO_BENEFICIARIES.filter((b) => b.country === country && b.payoutMethod === method);
  const [name, setName] = useState("");
  const [detail, setDetail] = useState("");
  const detailLabel = method === "mobile_money" ? "Numéro Mobile Money" : method === "card" ? "Numéro de carte" : "Ville de retrait";

  return (
    <div className="space-y-6">
      {saved.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-muted">Bénéficiaires enregistrés</p>
          {saved.map((b) => (
            <button key={b.id} onClick={() => onSelect({ name: b.name, detail: b.masked })} className="flex w-full items-center gap-3 rounded-xl border border-line bg-surface-2 p-3 text-left hover:border-brand/50">
              <span className="grid h-10 w-10 place-items-center rounded-full bg-surface-3 text-sm font-semibold">{b.name.split(" ").map((p) => p[0]).join("")}</span>
              <span className="flex-1">
                <span className="block font-medium">{b.name}</span>
                <span className="text-sm text-muted">{b.masked}</span>
              </span>
            </button>
          ))}
        </div>
      )}
      <form onSubmit={(e) => { e.preventDefault(); onSelect({ name, detail }); }} className="space-y-4">
        <p className="text-sm font-medium text-muted">Nouveau bénéficiaire</p>
        <Field label="Nom complet (comme sur sa pièce d'identité)">
          <Input value={name} onChange={(e) => setName(e.target.value)} required minLength={3} />
        </Field>
        <Field label={detailLabel}>
          <Input value={detail} onChange={(e) => setDetail(e.target.value)} required inputMode={method === "cash_pickup" ? "text" : "numeric"} />
        </Field>
        <Button type="submit" variant="action" className="w-full">Continuer</Button>
      </form>
    </div>
  );
}

function Outcome({ phase, quote, recipient }: { phase: "processing" | "done"; quote: DemoQuote; recipient: Recipient }) {
  return (
    <Card className="mx-auto max-w-lg p-10 text-center">
      {phase === "processing" ? (
        <>
          <Loader2 className="mx-auto h-14 w-14 animate-spin text-brand" />
          <h2 className="mt-6 text-xl font-semibold">Transfert en cours…</h2>
          <p className="mt-2 text-muted">Transmission à {PROVIDER_LABELS[quote.provider]}.</p>
        </>
      ) : (
        <>
          <span className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-brand/15">
            <CheckCircle2 className="h-12 w-12 text-brand" />
          </span>
          <h2 className="mt-6 text-xl font-semibold">Transfert envoyé</h2>
          <p className="mt-2 text-muted">
            {recipient.name} recevra <span className="font-semibold text-text">{formatMoney(quote.receiveAmount, quote.receiveCurrency)}</span> via {PROVIDER_LABELS[quote.provider]}.
          </p>
          <p className="mt-1 text-xs text-warning">Simulation : aucun fonds n&apos;a été déplacé.</p>
          <div className="mt-8 flex justify-center gap-3">
            <ButtonLink href="/transactions" variant="ghost">Voir l&apos;historique</ButtonLink>
            <ButtonLink href="/accueil">Retour à l&apos;accueil</ButtonLink>
          </div>
        </>
      )}
    </Card>
  );
}
