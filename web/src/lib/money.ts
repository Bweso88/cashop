/**
 * Montants en unités mineures (entiers), comme dans l'API. Jamais de calcul en flottant :
 * la conversion en décimal ne sert qu'à l'affichage.
 */
export type Currency = "XAF" | "EUR" | "USD" | "GBP";

export const MINOR_UNITS: Record<Currency, number> = { XAF: 0, EUR: 2, USD: 2, GBP: 2 };

export function formatMoney(amount: number, currency: Currency, options: { signed?: boolean } = {}): string {
  const digits = MINOR_UNITS[currency];
  const value = amount / 10 ** digits;
  const formatted = new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency,
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(Math.abs(value));
  if (!options.signed) return value < 0 ? `−${formatted}` : formatted;
  return `${value < 0 ? "−" : "+"}${formatted}`;
}

/** "1 250,5" + EUR -> 125050 ; renvoie null si la saisie est invalide. */
export function parseAmount(input: string, currency: Currency): number | null {
  const normalized = input.replace(/\s/g, "").replace(",", ".");
  if (!/^\d+(\.\d+)?$/.test(normalized)) return null;
  const [whole, fraction = ""] = normalized.split(".");
  const digits = MINOR_UNITS[currency];
  if (fraction.length > digits) return null;
  return Number(whole) * 10 ** digits + Number(fraction.padEnd(digits, "0") || 0);
}
