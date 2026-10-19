import type { PayoutMethod, TransferStatus } from "./transfers";

export const PAYOUT_LABELS: Record<PayoutMethod, string> = {
  cash_pickup: "Retrait en agence",
  mobile_money: "Mobile Money",
  bank_account: "Compte bancaire",
  card: "Carte bancaire",
};

const COUNTRIES: Record<string, { name: string; flag: string }> = {
  CG: { name: "Congo", flag: "🇨🇬" },
  CM: { name: "Cameroun", flag: "🇨🇲" },
  CI: { name: "Côte d'Ivoire", flag: "🇨🇮" },
  SN: { name: "Sénégal", flag: "🇸🇳" },
  GA: { name: "Gabon", flag: "🇬🇦" },
  FR: { name: "France", flag: "🇫🇷" },
  BE: { name: "Belgique", flag: "🇧🇪" },
  GB: { name: "Royaume-Uni", flag: "🇬🇧" },
  US: { name: "États-Unis", flag: "🇺🇸" },
};

export const countryName = (code: string) => COUNTRIES[code]?.name ?? code;
export const countryFlag = (code: string) => COUNTRIES[code]?.flag ?? "🌍";

export type Tone = "brand" | "warning" | "danger" | "info" | "neutral";

/** Seuls les statuts « livré » sont présentés comme une réussite (jamais le simple paiement). */
export function statusTone(status: TransferStatus): Tone {
  switch (status) {
    case "TRANSFER_AVAILABLE_FOR_PICKUP":
    case "PAYOUT_CONFIRMED":
      return "brand";
    case "FAILED":
    case "CANCELLED":
      return "danger";
    case "REFUNDED":
      return "neutral";
    case "REQUIRES_RECONCILIATION":
    case "REFUND_PENDING":
    case "CANCEL_PENDING":
      return "warning";
    default:
      return "info";
  }
}

export const dateTime = new Intl.DateTimeFormat("fr-FR", { dateStyle: "medium", timeStyle: "short" });
