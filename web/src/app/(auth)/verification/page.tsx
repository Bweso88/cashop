import type { Metadata } from "next";
import Link from "next/link";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { Suspense } from "react";
import { OtpForm } from "@/components/auth/OtpForm";
import { CHALLENGE_COOKIE, type OtpChallenge } from "@/lib/api/client";

export const metadata: Metadata = { title: "Vérification" };

const CHANNEL_TEXT: Record<string, (hint?: string | null) => string> = {
  sms: (hint) => `Saisissez le code envoyé par SMS au ${hint ?? "numéro enregistré"}.`,
  email: (hint) => `Saisissez le code envoyé à ${hint ?? "votre adresse e-mail"}.`,
  totp: () => "Saisissez le code affiché dans votre application d'authentification.",
};

async function ChallengeHint() {
  const raw = (await cookies()).get(CHALLENGE_COOKIE)?.value;
  if (!raw) redirect("/connexion");
  const challenge = JSON.parse(raw) as OtpChallenge;
  return <p className="mb-8 mt-1 text-muted">{CHANNEL_TEXT[challenge.channel ?? "sms"]?.(challenge.destination_hint)}</p>;
}

export default function VerificationPage() {
  return (
    <>
      <h1 className="text-2xl font-semibold">Vérification en deux étapes</h1>
      <Suspense fallback={<p className="mb-8 mt-1 text-muted">Chargement…</p>}>
        <ChallengeHint />
      </Suspense>
      <OtpForm />
      <p className="mt-8 text-center text-sm text-muted">
        Pas reçu de code ?{" "}
        <Link href="/connexion" className="font-medium text-brand hover:underline">Recommencer</Link>
      </p>
    </>
  );
}
