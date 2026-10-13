"use client";

import { useActionState } from "react";
import { setPinAction } from "@/lib/security-actions";
import { Button } from "@/components/ui/Button";
import { Field, FormError, Input } from "@/components/ui/Field";

export function PinForm({ pinSet }: { pinSet: boolean }) {
  const [state, action, pending] = useActionState(setPinAction, undefined);

  return (
    <form action={action} className="space-y-4">
      <FormError message={state?.error} />
      {state?.success && <p className="rounded-xl bg-brand/10 px-4 py-3 text-sm text-brand">PIN enregistré.</p>}
      {pinSet && (
        <Field label="PIN actuel" error={state?.fieldErrors?.current_pin}>
          <Input name="current_pin" type="password" inputMode="numeric" maxLength={6} pattern="\d{6}" required />
        </Field>
      )}
      <Field label={pinSet ? "Nouveau PIN" : "PIN à 6 chiffres"} error={state?.fieldErrors?.pin} hint="Évitez 123456, 000000 et les suites.">
        <Input name="pin" type="password" inputMode="numeric" maxLength={6} pattern="\d{6}" required />
      </Field>
      <Button type="submit" variant="action" disabled={pending}>{pinSet ? "Changer le PIN" : "Définir le PIN"}</Button>
    </form>
  );
}
