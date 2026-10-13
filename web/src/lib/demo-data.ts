import type { Currency } from "./money";

/**
 * DONNÉES DE DÉMONSTRATION — remplacées par l'API au fil des phases 4 à 8
 * (wallet, ledger, quotes, transferts). Aucun montant, frais ou taux n'est réel :
 * les tarifs et la couverture des providers restent NOT CONFIRMED (docs/PROVIDERS.md).
 */

export type ProviderCode = "visa_direct" | "moneygram" | "western_union" | "mtn_momo" | "airtel_money";
export type PayoutMethod = "mobile_money" | "cash_pickup" | "card" | "bank_account";
export type TransferStatus = "COMPLETED" | "PROCESSING" | "PENDING" | "FAILED" | "REFUNDED";

export const PROVIDER_LABELS: Record<ProviderCode, string> = {
  visa_direct: "Visa Direct",
  moneygram: "MoneyGram",
  western_union: "Western Union",
  mtn_momo: "MTN Mobile Money",
  airtel_money: "Airtel Money",
};

export const PAYOUT_LABELS: Record<PayoutMethod, string> = {
  mobile_money: "Mobile Money",
  cash_pickup: "Retrait en agence",
  card: "Carte bancaire",
  bank_account: "Compte bancaire",
};

export type Wallet = { id: string; label: string; currency: Currency; available: number; reserved: number; masked: string; gradient: string };

export const DEMO_WALLETS: Wallet[] = [
  { id: "w-xaf", label: "Portefeuille XAF", currency: "XAF", available: 485_000, reserved: 0, masked: "•••• 2606", gradient: "card-gradient-brand" },
  { id: "w-eur", label: "Portefeuille EUR", currency: "EUR", available: 260_700, reserved: 10_200, masked: "•••• 3876", gradient: "card-gradient-violet" },
  { id: "w-usd", label: "Portefeuille USD", currency: "USD", available: 54_020, reserved: 0, masked: "•••• 9367", gradient: "card-gradient-night" },
];

export type DemoTransfer = {
  id: string;
  direction: "out" | "in";
  counterparty: string;
  country: string;
  provider: ProviderCode;
  payoutMethod: PayoutMethod;
  sendAmount: number;
  sendCurrency: Currency;
  receiveAmount: number;
  receiveCurrency: Currency;
  fees: number;
  fxRate: string;
  status: TransferStatus;
  createdAt: string;
  reference: string;
  timeline: { status: string; label: string; at: string }[];
};

const t = (iso: string) => new Date(iso).toISOString();

export const DEMO_TRANSFERS: DemoTransfer[] = [
  {
    id: "0199a1c2-7d10-7a3e-9f00-000000000001", direction: "out", counterparty: "Aminata Kouassi", country: "CM",
    provider: "mtn_momo", payoutMethod: "mobile_money", sendAmount: 10_000, sendCurrency: "EUR", receiveAmount: 65_595, receiveCurrency: "XAF",
    fees: 249, fxRate: "655.957000", status: "COMPLETED", createdAt: t("2026-10-12T09:41:00Z"), reference: "CSH-7K2M-91QA",
    timeline: [
      { status: "AUTHORIZED", label: "Confirmé par PIN", at: t("2026-10-12T09:41:00Z") },
      { status: "SUBMITTED", label: "Transmis à l'opérateur", at: t("2026-10-12T09:41:03Z") },
      { status: "COMPLETED", label: "Crédité sur le compte Mobile Money", at: t("2026-10-12T09:41:41Z") },
    ],
  },
  {
    id: "0199a1c2-7d10-7a3e-9f00-000000000002", direction: "out", counterparty: "Paul Mbarga", country: "CM",
    provider: "western_union", payoutMethod: "cash_pickup", sendAmount: 25_000, sendCurrency: "EUR", receiveAmount: 163_989, receiveCurrency: "XAF",
    fees: 474, fxRate: "655.957000", status: "PROCESSING", createdAt: t("2026-10-11T16:05:00Z"), reference: "CSH-3PZ8-44LD",
    timeline: [
      { status: "AUTHORIZED", label: "Confirmé par biométrie", at: t("2026-10-11T16:05:00Z") },
      { status: "SUBMITTED", label: "Transmis au réseau de retrait", at: t("2026-10-11T16:05:05Z") },
      { status: "PROCESSING", label: "Disponible prochainement en agence", at: t("2026-10-11T16:06:00Z") },
    ],
  },
  {
    id: "0199a1c2-7d10-7a3e-9f00-000000000003", direction: "in", counterparty: "Owen Miller", country: "GB",
    provider: "visa_direct", payoutMethod: "card", sendAmount: 15_000, sendCurrency: "EUR", receiveAmount: 15_000, receiveCurrency: "EUR",
    fees: 0, fxRate: "1.000000", status: "COMPLETED", createdAt: t("2026-10-10T11:20:00Z"), reference: "CSH-9VQ1-07HX",
    timeline: [{ status: "COMPLETED", label: "Reçu sur votre portefeuille", at: t("2026-10-10T11:20:00Z") }],
  },
  {
    id: "0199a1c2-7d10-7a3e-9f00-000000000004", direction: "out", counterparty: "Grâce Okemba", country: "CG",
    provider: "airtel_money", payoutMethod: "mobile_money", sendAmount: 5_000, sendCurrency: "EUR", receiveAmount: 32_797, receiveCurrency: "XAF",
    fees: 174, fxRate: "655.957000", status: "FAILED", createdAt: t("2026-10-08T08:12:00Z"), reference: "CSH-1BX4-62NW",
    timeline: [
      { status: "SUBMITTED", label: "Transmis à l'opérateur", at: t("2026-10-08T08:12:02Z") },
      { status: "FAILED", label: "Numéro bénéficiaire introuvable", at: t("2026-10-08T08:12:30Z") },
      { status: "REVERSED", label: "Fonds restitués sur votre portefeuille", at: t("2026-10-08T08:12:31Z") },
    ],
  },
  {
    id: "0199a1c2-7d10-7a3e-9f00-000000000005", direction: "out", counterparty: "Jean-Marc Ndong", country: "GA",
    provider: "moneygram", payoutMethod: "cash_pickup", sendAmount: 40_000, sendCurrency: "EUR", receiveAmount: 262_382, receiveCurrency: "XAF",
    fees: 699, fxRate: "655.957000", status: "COMPLETED", createdAt: t("2026-10-05T14:30:00Z"), reference: "CSH-6HT2-80KE",
    timeline: [
      { status: "SUBMITTED", label: "Transmis au réseau de retrait", at: t("2026-10-05T14:30:04Z") },
      { status: "COMPLETED", label: "Retiré en agence", at: t("2026-10-06T10:02:00Z") },
    ],
  },
];

export type Beneficiary = { id: string; name: string; country: string; payoutMethod: PayoutMethod; masked: string; provider?: ProviderCode };

export const DEMO_BENEFICIARIES: Beneficiary[] = [
  { id: "b1", name: "Aminata Kouassi", country: "CM", payoutMethod: "mobile_money", masked: "+237 6•• ••• 842", provider: "mtn_momo" },
  { id: "b2", name: "Paul Mbarga", country: "CM", payoutMethod: "cash_pickup", masked: "Retrait à Douala" },
  { id: "b3", name: "Grâce Okemba", country: "CG", payoutMethod: "mobile_money", masked: "+242 05• ••• 117", provider: "airtel_money" },
  { id: "b4", name: "Owen Miller", country: "GB", payoutMethod: "card", masked: "Visa •••• 4242" },
];

export const COUNTRIES: { code: string; name: string; currency: Currency; flag: string }[] = [
  { code: "CM", name: "Cameroun", currency: "XAF", flag: "🇨🇲" },
  { code: "CG", name: "Congo", currency: "XAF", flag: "🇨🇬" },
  { code: "GA", name: "Gabon", currency: "XAF", flag: "🇬🇦" },
  { code: "TD", name: "Tchad", currency: "XAF", flag: "🇹🇩" },
  { code: "FR", name: "France", currency: "EUR", flag: "🇫🇷" },
  { code: "GB", name: "Royaume-Uni", currency: "GBP", flag: "🇬🇧" },
  { code: "US", name: "États-Unis", currency: "USD", flag: "🇺🇸" },
];

export const countryName = (code: string) => COUNTRIES.find((c) => c.code === code)?.name ?? code;
export const countryFlag = (code: string) => COUNTRIES.find((c) => c.code === code)?.flag ?? "🌍";

/** Corridors simulés (mêmes fixtures que les Mock providers du backend). */
const DEMO_CORRIDORS: { provider: ProviderCode; country: string; method: PayoutMethod; percentBps: number; fixed: number; delay: string }[] = [
  { provider: "mtn_momo", country: "CM", method: "mobile_money", percentBps: 150, fixed: 99, delay: "Quelques minutes" },
  { provider: "airtel_money", country: "CG", method: "mobile_money", percentBps: 150, fixed: 99, delay: "Quelques minutes" },
  { provider: "moneygram", country: "CM", method: "cash_pickup", percentBps: 150, fixed: 299, delay: "Sous 1 heure" },
  { provider: "moneygram", country: "GA", method: "cash_pickup", percentBps: 150, fixed: 299, delay: "Sous 1 heure" },
  { provider: "western_union", country: "CM", method: "cash_pickup", percentBps: 175, fixed: 249, delay: "Sous 1 heure" },
  { provider: "western_union", country: "TD", method: "cash_pickup", percentBps: 175, fixed: 249, delay: "Sous 1 heure" },
  { provider: "visa_direct", country: "FR", method: "card", percentBps: 100, fixed: 50, delay: "Jusqu'à 30 minutes" },
  { provider: "visa_direct", country: "GB", method: "card", percentBps: 100, fixed: 50, delay: "Jusqu'à 30 minutes" },
  { provider: "visa_direct", country: "US", method: "card", percentBps: 100, fixed: 50, delay: "Jusqu'à 30 minutes" },
];

/** Taux simulés, EUR → devise. EUR/XAF est la parité fixe officielle ; les autres sont illustratifs. */
const DEMO_RATES: Record<Currency, number> = { EUR: 1, XAF: 655.957, USD: 1.08, GBP: 0.85 };

export type DemoQuote = {
  id: string;
  provider: ProviderCode;
  method: PayoutMethod;
  sendAmount: number;
  sendCurrency: "EUR";
  fees: number;
  totalDebit: number;
  receiveAmount: number;
  receiveCurrency: Currency;
  fxRate: number;
  delay: string;
};

/** Montant en centimes d'euro -> offres simulées, triées par montant reçu décroissant. */
export function demoQuotes(sendAmountCents: number, country: string, method: PayoutMethod): DemoQuote[] {
  const target = COUNTRIES.find((c) => c.code === country)?.currency ?? "EUR";
  return DEMO_CORRIDORS.filter((c) => c.country === country && c.method === method)
    .map((c, i) => {
      const fees = c.fixed + Math.round((sendAmountCents * c.percentBps) / 10_000);
      const rate = DEMO_RATES[target];
      const receiveMajor = (sendAmountCents / 100) * rate;
      return {
        id: `demo-quote-${i}`,
        provider: c.provider,
        method,
        sendAmount: sendAmountCents,
        sendCurrency: "EUR" as const,
        fees,
        totalDebit: sendAmountCents + fees,
        receiveAmount: target === "XAF" ? Math.floor(receiveMajor) : Math.floor(receiveMajor * 100),
        receiveCurrency: target,
        fxRate: rate,
        delay: c.delay,
      };
    })
    .sort((a, b) => a.fees - b.fees);
}

export function availableMethods(country: string): PayoutMethod[] {
  return [...new Set(DEMO_CORRIDORS.filter((c) => c.country === country).map((c) => c.method))];
}

/** Solde total des portefeuilles de démo, converti en centimes d'euro. */
export function demoTotalInEur(): number {
  return DEMO_WALLETS.reduce((sum, w) => {
    const major = w.available / 10 ** (w.currency === "XAF" ? 0 : 2);
    return sum + Math.round((major / DEMO_RATES[w.currency]) * 100);
  }, 0);
}
