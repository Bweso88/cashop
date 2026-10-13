import { NextResponse, type NextRequest } from "next/server";

/**
 * Contrôle optimiste : sans cookie de session, les pages de l'espace client renvoient vers
 * la connexion. La vraie vérification (jeton valide) est faite côté serveur par l'API.
 */
export function proxy(request: NextRequest) {
  if (!request.cookies.has("cashop_session")) {
    const login = new URL("/connexion", request.url);
    login.searchParams.set("suite", request.nextUrl.pathname);
    return NextResponse.redirect(login);
  }
  return NextResponse.next();
}

export const config = {
  matcher: ["/accueil/:path*", "/envoyer/:path*", "/transactions/:path*", "/beneficiaires/:path*", "/kyc/:path*", "/securite/:path*", "/profil/:path*", "/recevoir/:path*"],
};
