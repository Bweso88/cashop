import { cn } from "@/lib/cn";

/**
 * Monogramme Cashop : un « C » ouvert et un point (la pièce qui circule).
 * Le dégradé est en CSS : pas de <defs> SVG partagés, qui disparaissent quand
 * l'élément qui les déclare est masqué (ex. barre latérale sur mobile).
 */
export function LogoMark({ className }: { className?: string }) {
  return (
    <span className={cn("grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand to-violet", className)} aria-hidden="true">
      <svg viewBox="0 0 40 40" className="h-full w-full">
        <path d="M28 14.5A9.5 9.5 0 1 0 28 25.5" fill="none" stroke="#0a0d14" strokeWidth="4.2" strokeLinecap="round" />
        <circle cx="29.5" cy="20" r="2.6" fill="#0a0d14" />
      </svg>
    </span>
  );
}

export function Logo({ className }: { className?: string }) {
  return (
    <span className={cn("inline-flex items-center gap-2.5", className)}>
      <LogoMark />
      <span className="text-xl font-semibold tracking-tight">Cashop</span>
    </span>
  );
}
