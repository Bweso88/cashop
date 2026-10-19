"use client";

import { useState, useTransition } from "react";
import { Loader2, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Field, FormError, Input, Select } from "@/components/ui/Field";
import { countryFlag, countryName } from "@/lib/labels";
import { deleteBeneficiaryAction, saveBeneficiaryAction } from "@/lib/transfer-actions";
import type { Beneficiary } from "@/lib/transfers";

const EMPTY = { first_name: "", last_name: "", country: "", phone: "", city: "", relationship: "" };

export function BeneficiaryManager({ beneficiaries, countries }: { beneficiaries: Beneficiary[]; countries: { code: string; name: string }[] }) {
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ ...EMPTY, country: countries[0]?.code ?? "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<string>();
  const set = (key: keyof typeof EMPTY) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm({ ...form, [key]: e.target.value });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    startTransition(async () => {
      const res = await saveBeneficiaryAction({ ...form, phone: form.phone.replace(/\s/g, "") });
      if (res.ok) {
        setForm({ ...EMPTY, country: form.country });
        setErrors({});
        setError(undefined);
        setOpen(false);
      } else {
        setErrors(res.fieldErrors ?? {});
        setError(res.error);
      }
    });
  }

  function remove(b: Beneficiary) {
    if (!confirm(`Supprimer ${b.first_name} ${b.last_name} de vos bénéficiaires ?`)) return;
    startTransition(async () => {
      const res = await deleteBeneficiaryAction(b.id);
      if (!res.ok) setError(res.error);
    });
  }

  return (
    <div className="space-y-6">
      {!open ? (
        <Button variant="action" onClick={() => setOpen(true)}><Plus className="h-4 w-4" /> Ajouter un bénéficiaire</Button>
      ) : (
        <Card>
          <form onSubmit={submit} className="space-y-4">
            <p className="text-xs text-faint">Nom et prénom exactement comme sur sa pièce d&apos;identité.</p>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Prénom" error={errors.first_name}><Input value={form.first_name} onChange={set("first_name")} required /></Field>
              <Field label="Nom" error={errors.last_name}><Input value={form.last_name} onChange={set("last_name")} required /></Field>
              <Field label="Pays" error={errors.country}>
                <Select value={form.country} onChange={set("country")}>
                  {countries.map((c) => <option key={c.code} value={c.code}>{countryFlag(c.code)} {c.name}</option>)}
                </Select>
              </Field>
              <Field label="Téléphone (avec indicatif)" error={errors.phone} hint="Obligatoire pour un versement Mobile Money."><Input value={form.phone} onChange={set("phone")} inputMode="tel" /></Field>
              <Field label="Ville" error={errors.city}><Input value={form.city} onChange={set("city")} /></Field>
              <Field label="Lien (facultatif)" error={errors.relationship}><Input value={form.relationship} onChange={set("relationship")} placeholder="Frère, mère…" /></Field>
            </div>
            <FormError message={error} />
            <div className="flex gap-3">
              <Button type="submit" disabled={pending}>{pending ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Enregistrer</Button>
              <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Annuler</Button>
            </div>
          </form>
        </Card>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        {beneficiaries.map((b) => (
          <Card key={b.id} className="flex items-center gap-4">
            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand/30 to-violet/30 font-semibold">{b.first_name[0]}{b.last_name[0]}</span>
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium">{b.first_name} {b.last_name}</p>
              <p className="truncate text-sm text-muted">{countryFlag(b.country)} {countryName(b.country)}{b.city ? ` · ${b.city}` : ""}</p>
              {b.phone && <p className="truncate text-sm text-muted">{b.phone}</p>}
            </div>
            <button onClick={() => remove(b)} disabled={pending} aria-label={`Supprimer ${b.first_name} ${b.last_name}`} className="rounded-lg p-2 text-muted hover:bg-danger/10 hover:text-danger">
              <Trash2 className="h-4 w-4" />
            </button>
          </Card>
        ))}
        {beneficiaries.length === 0 && <p className="text-muted">Aucun bénéficiaire enregistré.</p>}
      </div>
    </div>
  );
}
