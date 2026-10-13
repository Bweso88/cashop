import "server-only";
import { cookies } from "next/headers";
import type { components } from "./schema";

/**
 * Client de l'API Cashop, côté serveur uniquement (BFF).
 * Le jeton vit dans un cookie httpOnly : il n'est jamais exposé au JavaScript du navigateur.
 */
export type Profile = components["schemas"]["Profile"];
export type OtpChallenge = components["schemas"]["OtpChallenge"];
export type AuthToken = components["schemas"]["AuthToken"];
export type ApiErrorBody = components["schemas"]["Error"];

export const TOKEN_COOKIE = "cashop_session";
export const DEVICE_COOKIE = "cashop_device";
export const CHALLENGE_COOKIE = "cashop_challenge";

const API_URL = process.env.CASHOP_API_URL ?? "http://localhost:8000/api/v1";

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly body: ApiErrorBody,
  ) {
    super(body.message);
  }
}

export async function api<T>(path: string, init: { method?: string; body?: unknown; token?: string | null } = {}): Promise<T> {
  const token = init.token === undefined ? (await cookies()).get(TOKEN_COOKIE)?.value : init.token;

  let res: Response;
  try {
    res = await fetch(`${API_URL}${path}`, {
      method: init.method ?? "GET",
      headers: {
        Accept: "application/json",
        ...(init.body !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
      cache: "no-store",
    });
  } catch {
    throw new ApiError(503, { code: "API_UNREACHABLE", message: "Le service Cashop est momentanément indisponible." });
  }

  if (res.status === 204) return undefined as T;
  const data = await res.json().catch(() => ({ code: "INVALID_RESPONSE", message: "Réponse inattendue du serveur." }));
  if (!res.ok) throw new ApiError(res.status, data as ApiErrorBody);
  return data as T;
}
