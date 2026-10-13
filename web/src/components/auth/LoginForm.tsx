"use client";

import Link from "next/link";
import { useActionState } from "react";
import { loginAction } from "@/lib/auth-actions";
import { Button } from "@/components/ui/Button";
import { Field, FormError, Input } from "@/components/ui/Field";

export function LoginForm() {
  const [state, action, pending] = useActionState(loginAction, undefined);

  return (
    <form action={action} className="space-y-5">
      <FormError message={state?.error} />
      <Field label="E-mail ou téléphone" error={state?.fieldErrors?.login}>
        <Input name="login" autoComplete="username" placeholder="vous@exemple.com ou +237…" required />
      </Field>
      <Field label="Mot de passe" error={state?.fieldErrors?.password}>
        <Input name="password" type="password" autoComplete="current-password" required />
      </Field>
      <Button type="submit" className="w-full" disabled={pending}>
        {pending ? "Vérification…" : "Se connecter"}
      </Button>
      <p className="text-center text-sm text-muted">
        Pas encore de compte ?{" "}
        <Link href="/inscription" className="font-medium text-brand hover:underline">
          Créer un compte
        </Link>
      </p>
    </form>
  );
}
