"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";
import { Banknote, Check, ChevronLeft, CreditCard, Landmark, Loader2, Lock, Smartphone } from "lucide-react";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Field, FormError, Input, Select } from "@/components/ui/Field";
import { cn } from "@/lib/cn";
import { countryFlag, countryName, PAYOUT_LABELS } from "@/lib/labels";
import { formatMoney } from "@/lib/money";
import { createTransferAction, quoteAction, saveBeneficiaryAction } from "@/lib/transfer-actions";
import type { Beneficiary, FundingProvider, Network, PayoutMethod, Quote, Scenario } from "@/lib/transfers";

const STEPS = ["Montant", "Réseau", "Bénéficiaire", "Confirmation"] as const;
const METHOD_ICON: Record<PayoutMethod, typeof Smartphone> = { mobile_money: Smartphone, cash_pickup: Banknote, card: CreditCard, bank_account: Landmark };
const MIN_AMOUNT = 1_000;

type QuoteResult = { network: Network; quote?: Quote; error?: string };

export type SendWizardProps = {
  funding: FundingProvider[];
  networks: Network[];
  beneficiaries: Beneficiary[];
  scenarios: Scenario[];
  defaultMsisdn: string;
};

/**
 * Parcours d'envoi : montant et paiement Mobile Money → choix du réseau sur devis réels de l'API →
 * bénéficiaire → confirmation par PIN Cashop. Le débit est validé par le client sur son téléphone :
 * son PIN Mobile Money n'est jamais demandé ici.
 */
export function SendWizard({ funding, networks, beneficiaries: initialBeneficiaries, scenarios, defaultMsisdn }: SendWizardProps) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  const countries = [...new Map(networks.flatMap((n) => n.destinations).map((d) => [d.country, d])).values()];
  const [step, setStep] = useState(0);
  const [amountInput, setAmountInput] = useState("50000");
  const [country, setCountry] = useState(countries[0]?.country ?? "");
  const [method, setMethod] = useState<PayoutMethod>("cash_pickup");
  const [fundingCode, setFundingCode] = useState(funding.find((f) => f.code === "mtn_momo")?.code ?? funding[0]?.code ?? "");
  const [msisdn, setMsisdn] = useState(defaultMsisdn);
  const [quotes, setQuotes] = useState<QuoteResult[]>([]);
  const [quote, setQuote] = useState<Quote | null>(null);
  const [beneficiaries, setBeneficiaries] = useState(initialBeneficiaries);
  const [beneficiary, setBeneficiary] = useState<Beneficiary | null>(null);
  const [scenario, setScenario] = useState("");
  const [pin, setPin] = useState("");
  const [idempotencyKey, setIdempotencyKey] = useState("");
  const [error, setError] = useState<string>();

  const methods = [...new Set(networks.flatMap((n) => n.destinations.filter((d) => d.country === country).flatMap((d) => d.payout_methods.map((m) => m.code))))];
  const effectiveMethod = methods.includes(method) ? method : methods[0];
  const amount = /^\d+$/.test(amountInput.replace(/\s/g, "")) ? Number(amountInput.replace(/\s/g, "")) : null;
  const amountError = amount === null ? "Montant invalide (francs CFA, sans décimales)" : amount < MIN_AMOUNT ? `Minimum ${formatMoney(MIN_AMOUNT, "XAF")}` : undefined;
  const msisdnError = /^\+242\d{9}$/.test(msisdn.replace(/\s/g, "")) ? undefined : "Numéro congolais attendu : +242 suivi de 9 chiffres";
  const fundingName = funding.find((f) => f.code === fundingCode)?.name ?? "";

  function compare() {
    if (!effectiveMethod || amount === null) return;
    setError(undefined);
    const candidates = networks.filter((n) => n.destinations.some((d) => d.country === country && d.payout_methods.some((m) => m.code === effectiveMethod)));
    startTransition(async () => {
      const results = await Promise.all(candidates.map(async (network): Promise<QuoteResult> => {
        const res = await quoteAction({ network: network.code, funding_provider: fundingCode, destination_country: country, payout_method: effectiveMethod, send_amount: amount });
        return res.ok ? { network, quote: res.data } : { network, error: res.error };
      }));
      // Meilleure offre d'abord : le plus reçu, puis le moins débité ; les refus en dernier.
      results.sort((a, b) =>
        !a.quote || !b.quote ? (a.quote ? -1 : b.quote ? 1 : 0)
          : b.quote.receive.amount - a.quote.receive.amount || a.quote.total_debit.amount - b.quote.total_debit.amount);
      setQuotes(results);
      setStep(1);
    });
  }

  function chooseQuote(q: Quote) {
    setQuote(q);
    setIdempotencyKey(crypto.randomUUID()); // une clé par devis choisi
    setStep(2);
  }

  function chooseBeneficiary(b: Beneficiary) {
    setBeneficiary(b);
    setError(undefined);
    setStep(3);
  }

  function confirm() {
    if (!quote || !beneficiary) return;
    setError(undefined);
    startTransition(async () => {
      const res = await createTransferAction({
        idempotency_key: idempotencyKey,
        quote_id: quote.id,
        beneficiary_id: beneficiary.id,
        funding_msisdn: msisdn.replace(/\s/g, ""),
        pin,
        simulation_scenario: scenario || undefined,
      });
      if (res.ok) {
        router.push(`/transactions/${res.data.id}`);
        return;
      }
      setPin("");
      setError(res.code === "QUOTE_EXPIRED" ? "Ce devis a expiré ou a déjà servi. Revenez à l'étape « Réseau » pour en obtenir un nouveau." : Object.values(res.fieldErrors ?? {})[0] || res.error);
    });
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
      <Card className="min-w-0 p-6">
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
          <button onClick={() => { setError(undefined); setStep(step - 1); }} className="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-text">
            <ChevronLeft className="h-4 w-4" /> Retour
          </button>
        )}

        {step === 0 && (
          <div className="space-y-6">
            <Field label="Vous envoyez" error={amountInput ? amountError : undefined} hint="Montant en francs CFA, hors frais.">
              <div className="relative">
                <Input value={amountInput} onChange={(e) => setAmountInput(e.target.value.replace(/[^\d\s]/g, ""))} inputMode="numeric" className="pr-16 text-2xl font-semibold" aria-label="Montant en francs CFA" />
                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted">XAF</span>
              </div>
            </Field>
            <Field label="Pays de destination">
              <Select value={country} onChange={(e) => setCountry(e.target.value)}>
                {countries.map((c) => (
                  <option key={c.country} value={c.country}>{countryFlag(c.country)} {c.name} · {c.currency}</option>
                ))}
              </Select>
            </Field>
            <div>
              <p className="mb-2 text-sm font-medium text-muted">Mode de réception</p>
              <div className="grid gap-3 sm:grid-cols-2">
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
            <div>
              <p className="mb-2 text-sm font-medium text-muted">Payer avec</p>
              <div className="grid gap-3 sm:grid-cols-2">
                {funding.map((f) => (
                  <button key={f.code} type="button" onClick={() => setFundingCode(f.code)} className={cn("flex items-center gap-3 rounded-xl border p-4 text-left text-sm transition", fundingCode === f.code ? "border-brand bg-brand/10" : "border-line bg-surface-2 hover:border-faint")}>
                    <Smartphone className={cn("h-5 w-5", fundingCode === f.code ? "text-brand" : "text-muted")} />
                    {f.name}
                  </button>
                ))}
              </div>
            </div>
            <Field label={`Numéro ${fundingName} à débiter`} error={msisdn ? msisdnError : undefined} hint="Vous validerez le paiement sur ce téléphone.">
              <Input value={msisdn} onChange={(e) => setMsisdn(e.target.value)} inputMode="tel" autoComplete="tel" placeholder="+242 06 000 0000" />
            </Field>
            <FormError message={error} />
            <Button className="w-full" disabled={!!amountError || !!msisdnError || !effectiveMethod || pending} onClick={compare}>
              {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Comparer les réseaux
            </Button>
          </div>
        )}

        {step === 1 && (
          <div className="space-y-3">
            <p className="text-sm text-muted">
              {formatMoney(amount ?? 0, "XAF")} vers {countryFlag(country)} {countryName(country)} · {effectiveMethod ? PAYOUT_LABELS[effectiveMethod] : ""}
            </p>
            {quotes.map(({ network, quote: q, error: e }, i) =>
              q ? (
                <button key={network.code} type="button" onClick={() => chooseQuote(q)} className="flex w-full items-center gap-4 rounded-2xl border border-line bg-surface-2 p-4 text-left transition hover:border-brand/50">
                  <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 font-semibold">
                      {network.name}
                      {i === 0 && quotes.filter((r) => r.quote).length > 1 && <Badge tone="brand">Meilleure offre</Badge>}
                    </p>
                    <p className="mt-1 text-sm text-muted">Frais {formatMoney(q.total_fees.amount, q.total_fees.currency)} · total débité {formatMoney(q.total_debit.amount, q.total_debit.currency)}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-muted">Le bénéficiaire reçoit</p>
                    <p className="text-lg font-semibold tabular-nums text-brand">{formatMoney(q.receive.amount, q.receive.currency)}</p>
                  </div>
                </button>
              ) : (
                <div key={network.code} className="rounded-2xl border border-line p-4 text-sm">
                  <p className="font-semibold">{network.name}</p>
                  <p className="mt-1 text-muted">{e}</p>
                </div>
              ),
            )}
            {quotes.length === 0 && <p className="py-6 text-center text-muted">Aucun réseau ne propose ce corridor.</p>}
            {quotes.some((r) => r.quote?.is_simulated) && <p className="text-xs text-warning">Devis simulés : frais et taux fictifs.</p>}
          </div>
        )}

        {step === 2 && quote && (
          <BeneficiaryStep
            country={country}
            needsPhone={quote.payout_method === "mobile_money"}
            beneficiaries={beneficiaries.filter((b) => b.country === country)}
            onSelect={chooseBeneficiary}
            onCreated={(b) => { setBeneficiaries((list) => [b, ...list.filter((x) => x.id !== b.id)]); chooseBeneficiary(b); }}
          />
        )}

        {step === 3 && quote && beneficiary && (
          <div className="space-y-6">
            <dl className="divide-y divide-line rounded-2xl border border-line bg-surface-2 text-sm">
              {[
                ["Bénéficiaire", `${beneficiary.first_name} ${beneficiary.last_name}${beneficiary.city ? ` · ${beneficiary.city}` : ""}`],
                ["Réseau", `${quote.network.name} · ${PAYOUT_LABELS[quote.payout_method]}`],
                ["Paiement", `${quote.funding_provider.name} · ${msisdn}`],
                ["Montant envoyé", formatMoney(quote.send.amount, quote.send.currency)],
                ...quote.fees.map((f) => [f.label, formatMoney(f.amount, quote.send.currency)]),
                ["Taux appliqué", `1 XAF = ${Number(quote.fx_rate).toLocaleString("fr-FR", { maximumSignificantDigits: 8 })} ${quote.receive.currency}`],
                ["Total débité", formatMoney(quote.total_debit.amount, quote.total_debit.currency)],
              ].map(([k, v]) => (
                <div key={k} className="flex justify-between gap-4 px-4 py-3">
                  <dt className="text-muted">{k}</dt>
                  <dd className="text-right font-medium">{v}</dd>
                </div>
              ))}
              <div className="flex justify-between gap-4 px-4 py-4">
                <dt className="font-medium">Le bénéficiaire reçoit</dt>
                <dd className="text-lg font-semibold text-brand">{formatMoney(quote.receive.amount, quote.receive.currency)}</dd>
              </div>
            </dl>
            <p className="text-xs text-muted">Devis valable jusqu&apos;à {new Date(quote.expires_at).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}.</p>

            {scenarios.length > 0 && (
              <Field label="Scénario de démonstration" hint="Uniquement en simulation : permet de rejouer un refus, un délai ou un échec du réseau.">
                <Select value={scenario} onChange={(e) => setScenario(e.target.value)}>
                  <option value="">Succès (par défaut)</option>
                  {scenarios.filter((s) => s.code !== "SUCCESS").map((s) => (
                    <option key={s.code} value={s.code}>{s.label}</option>
                  ))}
                </Select>
              </Field>
            )}

            <Field label="Confirmez avec votre PIN Cashop" hint="Ce n'est pas votre code Mobile Money : celui-ci se saisit uniquement sur votre téléphone.">
              <div className="relative">
                <Lock className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <Input value={pin} onChange={(e) => setPin(e.target.value.replace(/\D/g, "").slice(0, 6))} type="password" inputMode="numeric" autoComplete="off" placeholder="••••••" className="pl-10 tracking-[0.5em]" aria-label="PIN Cashop" />
              </div>
            </Field>
            <FormError message={error} />
            <Button className="w-full" disabled={pin.length !== 6 || pending} onClick={confirm}>
              {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Payer {formatMoney(quote.total_debit.amount, quote.total_debit.currency)} avec {quote.funding_provider.name}
            </Button>
          </div>
        )}
      </Card>

      <aside className="space-y-4">
        <Card className="text-sm text-muted">
          <p className="mb-2 font-medium text-text">Comment ça marche</p>
          <ol className="list-decimal space-y-1.5 pl-4">
            <li>Vous confirmez avec votre PIN Cashop.</li>
            <li>{fundingName || "Votre opérateur"} vous demande de valider le paiement sur votre téléphone (5 minutes).</li>
            <li>Une fois le paiement confirmé, Cashop transmet le transfert au réseau choisi.</li>
            <li>Si le réseau refuse, vous êtes remboursé intégralement, frais compris.</li>
          </ol>
        </Card>
        <Card className="text-sm text-muted">
          <p className="mb-2 font-medium text-text">Simulation</p>
          Les paiements, frais, taux et références sont <strong className="text-warning">simulés</strong> : aucun fonds réel n&apos;est déplacé et les références « SIM- » ne sont reconnues par aucun réseau.
        </Card>
      </aside>
    </div>
  );
}

function BeneficiaryStep({ country, needsPhone, beneficiaries, onSelect, onCreated }: {
  country: string;
  needsPhone: boolean;
  beneficiaries: Beneficiary[];
  onSelect: (b: Beneficiary) => void;
  onCreated: (b: Beneficiary) => void;
}) {
  const [pending, startTransition] = useTransition();
  const [form, setForm] = useState({ first_name: "", last_name: "", phone: "", city: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string>();
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [key]: e.target.value });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    startTransition(async () => {
      const res = await saveBeneficiaryAction({ ...form, phone: form.phone.replace(/\s/g, ""), country });
      if (res.ok) onCreated(res.data);
      else { setErrors(res.fieldErrors ?? {}); setError(res.error); }
    });
  }

  return (
    <div className="space-y-6">
      {beneficiaries.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm font-medium text-muted">Bénéficiaires enregistrés · {countryName(country)}</p>
          {beneficiaries.map((b) => {
            const missingPhone = needsPhone && !b.phone;
            return (
              <button key={b.id} disabled={missingPhone} onClick={() => onSelect(b)} className="flex w-full items-center gap-3 rounded-xl border border-line bg-surface-2 p-3 text-left hover:border-brand/50 disabled:cursor-not-allowed disabled:opacity-50">
                <span className="grid h-10 w-10 place-items-center rounded-full bg-surface-3 text-sm font-semibold">{b.first_name[0]}{b.last_name[0]}</span>
                <span className="min-w-0 flex-1">
                  <span className="block font-medium">{b.first_name} {b.last_name}</span>
                  <span className="text-sm text-muted">{missingPhone ? "Numéro manquant pour un versement mobile" : [b.city, b.phone].filter(Boolean).join(" · ") || "—"}</span>
                </span>
              </button>
            );
          })}
        </div>
      )}
      <form onSubmit={submit} className="space-y-4">
        <p className="text-sm font-medium text-muted">Nouveau bénéficiaire</p>
        <p className="text-xs text-faint">Nom et prénom exactement comme sur sa pièce d&apos;identité : ils seront contrôlés au retrait.</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Prénom" error={errors.first_name}><Input value={form.first_name} onChange={set("first_name")} required /></Field>
          <Field label="Nom" error={errors.last_name}><Input value={form.last_name} onChange={set("last_name")} required /></Field>
        </div>
        <Field label={needsPhone ? "Numéro Mobile Money (avec indicatif)" : "Téléphone (facultatif)"} error={errors.phone}>
          <Input value={form.phone} onChange={set("phone")} required={needsPhone} inputMode="tel" placeholder="+237 6 90 00 00 00" />
        </Field>
        <Field label="Ville (facultatif)" error={errors.city}><Input value={form.city} onChange={set("city")} /></Field>
        <FormError message={error} />
        <Button type="submit" variant="action" className="w-full" disabled={pending}>
          {pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Enregistrer et continuer
        </Button>
      </form>
    </div>
  );
}
