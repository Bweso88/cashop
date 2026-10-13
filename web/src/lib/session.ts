import "server-only";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { api, ApiError, TOKEN_COOKIE, type Profile } from "./api/client";

const isProduction = process.env.NODE_ENV === "production";

export const cookieOptions = {
  httpOnly: true,
  secure: isProduction,
  sameSite: "lax" as const,
  path: "/",
};

export async function setSessionToken(token: string, expiresAt?: string) {
  (await cookies()).set(TOKEN_COOKIE, token, {
    ...cookieOptions,
    ...(expiresAt ? { expires: new Date(expiresAt) } : {}),
  });
}

export async function clearSession() {
  (await cookies()).delete(TOKEN_COOKIE);
}

/**
 * Utilisateur courant (couche d'accès aux données). Relit le profil auprès de l'API :
 * un jeton expiré ou révoqué renvoie vers la connexion.
 */
export async function getCurrentUser(): Promise<Profile> {
  if (!(await cookies()).get(TOKEN_COOKIE)) redirect("/connexion");
  try {
    return await api<Profile>("/profile");
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) redirect("/session/expiree");
    throw error;
  }
}
