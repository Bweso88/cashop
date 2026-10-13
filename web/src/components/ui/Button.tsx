import Link from "next/link";
import type { ComponentProps } from "react";
import { cn } from "@/lib/cn";

type Variant = "primary" | "action" | "ghost" | "danger";

const variants: Record<Variant, string> = {
  primary: "bg-brand text-ink hover:bg-brand-strong",
  action: "bg-action text-text hover:bg-action-hover",
  ghost: "bg-transparent text-text border border-line hover:bg-surface-2",
  danger: "bg-danger/15 text-danger hover:bg-danger/25",
};

const base =
  "inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50";

export function Button({ variant = "primary", className, ...props }: ComponentProps<"button"> & { variant?: Variant }) {
  return <button className={cn(base, variants[variant], className)} {...props} />;
}

export function ButtonLink({ variant = "primary", className, ...props }: ComponentProps<typeof Link> & { variant?: Variant }) {
  return <Link className={cn(base, variants[variant], className)} {...props} />;
}
