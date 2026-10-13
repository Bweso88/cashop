"use server";

import { revalidatePath } from "next/cache";
import { api, ApiError } from "./api/client";
import type { FormState } from "./auth-actions";

function fail(error: unknown): FormState {
  if (error instanceof ApiError) {
    const fieldErrors = Object.fromEntries(Object.entries(error.body.errors ?? {}).map(([k, v]) => [k, v[0] ?? ""]));
    return { error: error.body.message, fieldErrors };
  }
  throw error;
}

export type PinState = (FormState & { success?: boolean }) | undefined;

export async function setPinAction(_: PinState, form: FormData): Promise<PinState> {
  const current = String(form.get("current_pin") ?? "");
  try {
    await api("/auth/pin", { method: "PUT", body: { pin: String(form.get("pin") ?? ""), ...(current ? { current_pin: current } : {}) } });
  } catch (error) {
    return fail(error);
  }
  revalidatePath("/securite");
  return { success: true };
}

export type TotpSetup = { secret: string; otpauth_uri: string };
export type MfaState = (FormState & { setup?: TotpSetup; enabled?: boolean }) | undefined;

export async function startTotpAction(): Promise<MfaState> {
  try {
    return { setup: await api<TotpSetup>("/auth/mfa/totp", { method: "POST" }) };
  } catch (error) {
    return fail(error);
  }
}

export async function confirmTotpAction(state: MfaState, form: FormData): Promise<MfaState> {
  try {
    await api("/auth/mfa/totp/confirm", { method: "POST", body: { code: String(form.get("code") ?? "") } });
  } catch (error) {
    return { ...fail(error), setup: state?.setup };
  }
  revalidatePath("/securite");
  return { enabled: true };
}
