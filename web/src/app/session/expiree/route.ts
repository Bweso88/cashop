import { NextResponse, type NextRequest } from "next/server";
import { TOKEN_COOKIE } from "@/lib/api/client";

/** Session expirée ou révoquée : on efface le cookie puis on renvoie vers la connexion. */
export function GET(request: NextRequest) {
  const response = NextResponse.redirect(new URL("/connexion?expiree=1", request.url));
  response.cookies.delete(TOKEN_COOKIE);
  return response;
}
