"use client";

import Link from "next/link";
import { useActionState } from "react";
import { registerAction } from "@/lib/auth-actions";
import { Button } from "@/components/ui/Button";
import { Field, FormError, Input, Select } from "@/components/ui/Field";

const COUNTRIES = [
  ["CM", "Cameroun"], ["CG", "Congo"], ["GA", "Gabon"], ["TD", "Tchad"], ["CF", "Centrafrique"], ["GQ", "Guinée équatoriale"],
  ["CD", "RD Congo"], ["CI", "Côte d'Ivoire"], ["SN", "Sénégal"], ["FR", "France"], ["BE", "Belgique"], ["DE", "Allemagne"],
  ["GB", "Royaume-Uni"], ["US", "États-Unis"],
];

export function RegisterForm() {
  const [state, action, pending] = useActionState(registerAction, undefined);
  const e = state?.fieldErrors ?? {};

  return (
    <form action={action} className="space-y-4">
      <FormError message={state?.error} />
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Prénom" error={e.first_name}>
          <Input name="first_name" autoComplete="given-name" required />
        </Field>
        <Field label="Nom" error={e.last_name}>
          <Input name="last_name" autoComplete="family-name" required />
        </Field>
      </div>
      <Field label="E-mail" error={e.email}>
        <Input name="email" type="email" autoComplete="email" required />
      </Field>
      <div className="grid gap-4 sm:grid-cols-[0.9fr_1.1fr]">
        <Field label="Pays de résidence" error={e.country}>
          <Select name="country" defaultValue="CM" required>
            {COUNTRIES.map(([code, name]) => (
              <option key={code} value={code}>{name}</option>
            ))}
          </Select>
        </Field>
        <Field label="Téléphone" error={e.phone} hint="Format international : +237 6…">
          <Input name="phone" type="tel" autoComplete="tel" placeholder="+237 690 00 00 00" required />
        </Field>
      </div>
      <Field label="Mot de passe" error={e.password} hint="10 caractères minimum, avec lettres et chiffres.">
        <Input name="password" type="password" autoComplete="new-password" minLength={10} required />
      </Field>
      <Button type="submit" className="w-full" disabled={pending}>
        {pending ? "Création…" : "Créer mon compte"}
      </Button>
      <p className="text-center text-sm text-muted">
        Déjà inscrit ?{" "}
        <Link href="/connexion" className="font-medium text-brand hover:underline">Se connecter</Link>
      </p>
    </form>
  );
}
