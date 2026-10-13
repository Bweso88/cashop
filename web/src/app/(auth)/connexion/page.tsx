import type { Metadata } from "next";
import { Suspense } from "react";
import { LoginForm } from "@/components/auth/LoginForm";

export const metadata: Metadata = { title: "Connexion" };

async function ExpiredNotice({ searchParams }: { searchParams: Promise<{ expiree?: string }> }) {
  const { expiree } = await searchParams;
  if (!expiree) return null;
  return <p className="mb-5 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">Votre session a expiré. Reconnectez-vous.</p>;
}

export default function LoginPage({ searchParams }: { searchParams: Promise<{ expiree?: string }> }) {
  return (
    <>
      <h1 className="text-2xl font-semibold">Bon retour</h1>
      <p className="mb-8 mt-1 text-muted">Connectez-vous pour gérer votre argent.</p>
      <Suspense>
        <ExpiredNotice searchParams={searchParams} />
      </Suspense>
      <LoginForm />
      <p className="mt-10 text-center text-xs text-faint">Un code de vérification vous sera demandé à chaque connexion.</p>
    </>
  );
}
