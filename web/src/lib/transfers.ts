import "server-only";
import { api } from "./api/client";
import type { Currency } from "./money";

/**
 * Types et lecture des données du parcours d'envoi (API /api/v1). Côté serveur uniquement :
 * le jeton de session reste dans le cookie httpOnly.
 */
export type Money = { amount: number; currency: Currency };
export type PayoutMethod = "cash_pickup" | "mobile_money" | "bank_account" | "card";
export type Named = { code: string; name: string };

export type FundingProvider = Named & { environment: string; is_simulation: boolean };
export type Network = Named & {
  environment: string;
  is_simulation: boolean;
  destinations: { country: string; name: string; currency: Currency; payout_methods: { code: PayoutMethod; label: string }[] }[];
};
export type Scenario = { code: string; label: string };

export type Beneficiary = {
  id: string;
  first_name: string;
  last_name: string;
  display_name: string;
  country: string;
  phone: string | null;
  city: string | null;
  relationship: string | null;
  last_used_at: string | null;
};

export type FeeLine = { component: string; label: string; amount: number };
export type Quote = {
  id: string;
  network: Named;
  funding_provider: Named;
  environment: string;
  is_simulated: boolean;
  simulation_notice: string | null;
  send: Money;
  fees: FeeLine[];
  total_fees: Money;
  total_debit: Money;
  receive: Money;
  fx_rate: string;
  destination_country: string;
  payout_method: PayoutMethod;
  expires_at: string;
};

export type TransferStatus =
  | "INITIATED" | "QUOTE_PENDING" | "QUOTE_CONFIRMED" | "PAYMENT_PENDING" | "PAYMENT_CONFIRMED"
  | "TRANSFER_PENDING" | "TRANSFER_CONFIRMED" | "TRANSFER_AVAILABLE_FOR_PICKUP" | "PAYOUT_PENDING"
  | "PAYOUT_CONFIRMED" | "CANCEL_PENDING" | "REFUND_PENDING" | "REFUNDED" | "FAILED" | "CANCELLED"
  | "REQUIRES_RECONCILIATION";

export type Transfer = {
  id: string;
  status: TransferStatus;
  status_label: string;
  is_terminal: boolean;
  is_delivered: boolean;
  environment: string;
  is_simulation: boolean;
  simulation_notice: string | null;
  simulation_scenario: string | null;
  network: Named;
  funding: { provider: Named; msisdn_masked: string; payment_status: "PENDING" | "CONFIRMED" | "FAILED" | "UNKNOWN" | null; payment_reference: string | null };
  beneficiary: { id: string; name: string; country: string };
  send: Money;
  total_fees: Money;
  total_debit: Money;
  receive: Money;
  fx_rate: string;
  payout_method: PayoutMethod;
  destination_country: string;
  external_reference: string;
  provider_reference: string | null;
  pickup_code: string | null;
  failure: { code: string; message: string | null } | null;
  refund?: { status: string; amount: number; currency: Currency; reference: string | null } | null;
  receipts?: { kind: "TRANSFER" | "REFUND"; number: string }[];
  timeline?: { from: string | null; to: TransferStatus; label: string; source: string; at: string }[];
  created_at: string;
  updated_at: string;
};

export type Receipt = {
  number: string;
  kind: "TRANSFER" | "REFUND";
  is_simulation: boolean;
  issued_at: string;
  content: {
    status_label: string;
    simulation_notice: string | null;
    sender: string;
    beneficiary: string;
    destination_country: string;
    network: string;
    funding: string;
    payout_method: PayoutMethod;
    send_amount: number;
    send_currency: Currency;
    fees: FeeLine[];
    total_fees: number;
    total_debit: number;
    receive_amount: number;
    receive_currency: Currency;
    fx_rate: string;
    external_reference: string;
    provider_reference: string | null;
    pickup_code: string | null;
    refunded_amount: number | null;
    created_at: string;
  };
};

export type TransferFilter = "all" | "in_progress" | "completed";

export async function getCatalog() {
  const [funding, networks, scenarios] = await Promise.all([
    api<{ data: FundingProvider[] }>("/catalog/funding-providers"),
    api<{ data: Network[] }>("/catalog/networks"),
    api<{ enabled: boolean; data: Scenario[] }>("/catalog/simulation-scenarios"),
  ]);
  return { funding: funding.data, networks: networks.data, scenarios: scenarios.enabled ? scenarios.data : [] };
}

export async function getBeneficiaries(): Promise<Beneficiary[]> {
  return (await api<{ data: Beneficiary[] }>("/beneficiaries")).data;
}

export async function getTransfers(state: TransferFilter = "all", q = "", perPage = 20) {
  const params = new URLSearchParams({ state, per_page: String(perPage) });
  if (q) params.set("q", q);
  return api<{ data: Transfer[]; meta: { current_page: number; last_page: number; total: number } }>(`/transfers?${params}`);
}

export async function getTransfer(id: string): Promise<Transfer> {
  return api<Transfer>(`/transfers/${encodeURIComponent(id)}`);
}

export async function getReceipts(id: string): Promise<Receipt[]> {
  return (await api<{ data: Receipt[] }>(`/transfers/${encodeURIComponent(id)}/receipt`)).data;
}
