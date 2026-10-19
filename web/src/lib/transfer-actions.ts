"use server";

import { revalidatePath } from "next/cache";
import { api, ApiError } from "./api/client";
import type { Beneficiary, PayoutMethod, Quote, Transfer } from "./transfers";

export type ActionResult<T> = { ok: true; data: T } | { ok: false; error: string; code?: string; fieldErrors?: Record<string, string> };

function failure(error: unknown): { ok: false; error: string; code?: string; fieldErrors?: Record<string, string> } {
  if (error instanceof ApiError) {
    const fieldErrors = Object.fromEntries(Object.entries(error.body.errors ?? {}).map(([k, v]) => [k, v[0] ?? ""]));
    return { ok: false, error: error.body.message, code: error.body.code, fieldErrors };
  }
  throw error;
}

export async function quoteAction(input: {
  network: string;
  funding_provider: string;
  destination_country: string;
  payout_method: PayoutMethod;
  send_amount: number;
}): Promise<ActionResult<Quote>> {
  try {
    return { ok: true, data: await api<Quote>("/transfers/quote", { method: "POST", body: input }) };
  } catch (error) {
    return failure(error);
  }
}

export async function saveBeneficiaryAction(input: {
  first_name: string;
  last_name: string;
  country: string;
  phone?: string;
  city?: string;
  relationship?: string;
}): Promise<ActionResult<Beneficiary>> {
  try {
    const body = Object.fromEntries(Object.entries(input).filter(([, v]) => v !== undefined && v !== ""));
    const beneficiary = await api<Beneficiary>("/beneficiaries", { method: "POST", body });
    revalidatePath("/beneficiaires");
    return { ok: true, data: beneficiary };
  } catch (error) {
    return failure(error);
  }
}

export async function deleteBeneficiaryAction(id: string): Promise<ActionResult<null>> {
  try {
    await api(`/beneficiaries/${encodeURIComponent(id)}`, { method: "DELETE" });
    revalidatePath("/beneficiaires");
    return { ok: true, data: null };
  } catch (error) {
    return failure(error);
  }
}

/**
 * Confirmation de l'envoi. La clé d'idempotence est générée une fois par le navigateur pour ce
 * devis : un double clic ou un nouvel essai réseau ne crée jamais un second débit.
 */
export async function createTransferAction(input: {
  idempotency_key: string;
  quote_id: string;
  beneficiary_id: string;
  funding_msisdn: string;
  pin: string;
  simulation_scenario?: string;
}): Promise<ActionResult<Transfer>> {
  const { idempotency_key, ...body } = input;
  try {
    const transfer = await api<Transfer>("/transfers", {
      method: "POST",
      body: Object.fromEntries(Object.entries(body).filter(([, v]) => v !== undefined && v !== "")),
      headers: { "Idempotency-Key": idempotency_key },
    });
    revalidatePath("/transactions");
    return { ok: true, data: transfer };
  } catch (error) {
    return failure(error);
  }
}

/** Relit le statut auprès des prestataires (bouton « Actualiser » et suivi automatique). */
export async function refreshTransferAction(id: string): Promise<ActionResult<Transfer>> {
  try {
    return { ok: true, data: await api<Transfer>(`/transfers/${encodeURIComponent(id)}/refresh`, { method: "POST" }) };
  } catch (error) {
    return failure(error);
  }
}
