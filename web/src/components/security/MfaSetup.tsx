"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import QRCode from "qrcode";
import { confirmTotpAction, startTotpAction, type MfaState } from "@/lib/security-actions";
import { Button } from "@/components/ui/Button";
import { Field, FormError, Input } from "@/components/ui/Field";

export function MfaSetup({ enabled }: { enabled: boolean }) {
  const [setupState, setSetupState] = useState<MfaState>(undefined);
  const [starting, startTransition] = useTransition();
  const [state, confirm, confirming] = useActionState(confirmTotpAction, undefined);
  const [qr, setQr] = useState<string>("");

  const setup = state?.setup ?? setupState?.setup;

  useEffect(() => {
    if (setup) QRCode.toString(setup.otpauth_uri, { type: "svg", margin: 1 }).then(setQr);
  }, [setup]);

  if (enabled || state?.enabled) {
    return <p className="rounded-xl bg-brand/10 px-4 py-3 text-sm text-brand">La double authentification est activée sur votre compte.</p>;
  }

  if (!setup) {
    return (
      <div className="space-y-4">
        <FormError message={setupState?.error} />
        <p className="text-sm text-muted">Protégez votre compte avec une application comme Google Authenticator, Microsoft Authenticator ou 1Password.</p>
        <Button variant="action" disabled={starting} onClick={() => startTransition(async () => setSetupState(await startTotpAction()))}>
          Activer la double authentification
        </Button>
      </div>
    );
  }

  return (
    <form action={confirm} className="grid gap-6 sm:grid-cols-[180px_1fr]">
      <div className="h-[180px] w-[180px] rounded-xl bg-white p-2" dangerouslySetInnerHTML={{ __html: qr }} />
      <div className="space-y-4">
        <p className="text-sm text-muted">Scannez ce QR code, ou saisissez la clé : <code className="break-all rounded bg-surface-3 px-1.5 py-0.5 text-xs text-text">{setup.secret}</code></p>
        <FormError message={state?.error} />
        <Field label="Code à 6 chiffres affiché par l'application">
          <Input name="code" inputMode="numeric" maxLength={6} pattern="\d{6}" required autoComplete="one-time-code" />
        </Field>
        <Button type="submit" disabled={confirming}>Confirmer</Button>
      </div>
    </form>
  );
}
