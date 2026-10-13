"use server";

import { cookies, headers } from "next/headers";
import { redirect } from "next/navigation";
import { api, ApiError, CHALLENGE_COOKIE, DEVICE_COOKIE, TOKEN_COOKIE, type AuthToken, type OtpChallenge } from "./api/client";
import { clearSession, cookieOptions, setSessionToken } from "./session";

export type FormState = { error?: string; fieldErrors?: Record<string, string> } | undefined;

/** Identifiant stable de ce navigateur, nécessaire à l'API (un jeton par appareil). */
async function deviceId(): Promise<string> {
  const jar = await cookies();
  let id = jar.get(DEVICE_COOKIE)?.value;
  if (!id) {
    id = `web-${crypto.randomUUID()}`;
    jar.set(DEVICE_COOKIE, id, { ...cookieOptions, maxAge: 60 * 60 * 24 * 365 });
  }
  return id;
}

async function device() {
  const ua = (await headers()).get("user-agent") ?? "Navigateur";
  return { device_id: await deviceId(), platform: "web", name: ua.slice(0, 90) };
}

function toFormState(error: unknown): FormState {
  if (error instanceof ApiError) {
    const fieldErrors = Object.fromEntries(Object.entries(error.body.errors ?? {}).map(([k, v]) => [k, v[0] ?? ""]));
    return { error: error.body.message, fieldErrors };
  }
  throw error;
}

async function rememberChallenge(challenge: OtpChallenge) {
  (await cookies()).set(CHALLENGE_COOKIE, JSON.stringify(challenge), { ...cookieOptions, maxAge: 10 * 60 });
}

export async function loginAction(_: FormState, form: FormData): Promise<FormState> {
  try {
    const challenge = await api<OtpChallenge>("/auth/login", {
      method: "POST",
      token: null,
      body: { login: String(form.get("login") ?? ""), password: String(form.get("password") ?? ""), device: await device() },
    });
    await rememberChallenge(challenge);
  } catch (error) {
    return toFormState(error);
  }
  redirect("/verification");
}

export async function registerAction(_: FormState, form: FormData): Promise<FormState> {
  try {
    const challenge = await api<OtpChallenge>("/auth/register", {
      method: "POST",
      token: null,
      body: {
        email: String(form.get("email") ?? ""),
        phone: String(form.get("phone") ?? "").replace(/\s/g, ""),
        password: String(form.get("password") ?? ""),
        first_name: String(form.get("first_name") ?? ""),
        last_name: String(form.get("last_name") ?? ""),
        country: String(form.get("country") ?? ""),
        device: await device(),
      },
    });
    await rememberChallenge(challenge);
  } catch (error) {
    return toFormState(error);
  }
  redirect("/verification");
}

export async function verifyOtpAction(_: FormState, form: FormData): Promise<FormState> {
  const jar = await cookies();
  const raw = jar.get(CHALLENGE_COOKIE)?.value;
  if (!raw) redirect("/connexion");
  const challenge = JSON.parse(raw) as OtpChallenge;

  try {
    const auth = await api<AuthToken>("/auth/verify-otp", {
      method: "POST",
      token: null,
      body: { challenge_id: challenge.challenge_id, code: String(form.get("code") ?? "") },
    });
    await setSessionToken(auth.token!, auth.expires_at);
    jar.delete(CHALLENGE_COOKIE);
  } catch (error) {
    return toFormState(error);
  }
  redirect("/accueil");
}

export async function logoutAction() {
  try {
    if ((await cookies()).get(TOKEN_COOKIE)) await api("/auth/logout", { method: "POST" });
  } catch {
    // Jeton déjà expiré : on efface quand même la session locale.
  }
  await clearSession();
  redirect("/connexion");
}
