"use client";

import { useActionState, useRef, useState } from "react";
import { verifyOtpAction } from "@/lib/auth-actions";
import { Button } from "@/components/ui/Button";
import { FormError } from "@/components/ui/Field";

const LENGTH = 6;

/** Saisie du code en 6 cases ; le collage d'un code complet est pris en charge. */
export function OtpForm() {
  const [state, action, pending] = useActionState(verifyOtpAction, undefined);
  const [digits, setDigits] = useState<string[]>(Array(LENGTH).fill(""));
  const refs = useRef<Array<HTMLInputElement | null>>([]);

  function update(index: number, value: string) {
    const clean = value.replace(/\D/g, "");
    if (clean.length > 1) {
      const next = clean.slice(0, LENGTH).split("");
      setDigits([...next, ...Array(LENGTH - next.length).fill("")]);
      refs.current[Math.min(next.length, LENGTH - 1)]?.focus();
      return;
    }
    const next = [...digits];
    next[index] = clean;
    setDigits(next);
    if (clean && index < LENGTH - 1) refs.current[index + 1]?.focus();
  }

  return (
    <form action={action} className="space-y-6">
      <FormError message={state?.error} />
      <input type="hidden" name="code" value={digits.join("")} />
      <div className="flex justify-between gap-2" role="group" aria-label="Code de vérification">
        {digits.map((digit, i) => (
          <input
            key={i}
            ref={(el) => { refs.current[i] = el; }}
            value={digit}
            onChange={(e) => update(i, e.target.value)}
            onKeyDown={(e) => e.key === "Backspace" && !digit && i > 0 && refs.current[i - 1]?.focus()}
            inputMode="numeric"
            autoComplete={i === 0 ? "one-time-code" : "off"}
            aria-label={`Chiffre ${i + 1}`}
            className="h-14 w-full rounded-xl border border-line bg-surface-2 text-center text-xl font-semibold outline-none focus:border-brand focus:ring-2 focus:ring-brand/30"
          />
        ))}
      </div>
      <Button type="submit" className="w-full" disabled={pending || digits.join("").length !== LENGTH}>
        {pending ? "Vérification…" : "Valider"}
      </Button>
    </form>
  );
}
