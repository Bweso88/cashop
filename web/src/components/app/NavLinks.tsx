"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ArrowDownLeft, ArrowLeftRight, Home, IdCard, Send, ShieldCheck, UserRound, Users } from "lucide-react";
import { cn } from "@/lib/cn";

export const NAV = [
  { href: "/accueil", label: "Accueil", icon: Home },
  { href: "/envoyer", label: "Envoyer", icon: Send },
  { href: "/recevoir", label: "Recevoir", icon: ArrowDownLeft },
  { href: "/transactions", label: "Transactions", icon: ArrowLeftRight },
  { href: "/beneficiaires", label: "Bénéficiaires", icon: Users },
  { href: "/kyc", label: "Vérification d'identité", icon: IdCard },
  { href: "/securite", label: "Sécurité", icon: ShieldCheck },
  { href: "/profil", label: "Profil", icon: UserRound },
];

/** Navigation avec l'onglet actif surligné (lit l'URL : à placer dans un <Suspense>). */
export function NavLinks({ compact = false }: { compact?: boolean }) {
  return <NavList compact={compact} pathname={usePathname()} />;
}

/** Même navigation sans état actif : sert de repli pré-rendu. */
export function NavList({ compact = false, pathname = "" }: { compact?: boolean; pathname?: string }) {
  return (
    <nav className={cn(compact ? "flex justify-around" : "space-y-1")}>
      {NAV.filter((item) => !compact || ["/accueil", "/envoyer", "/transactions", "/profil"].includes(item.href)).map(({ href, label, icon: Icon }) => {
        const active = pathname === href || pathname.startsWith(`${href}/`);
        return (
          <Link
            key={href}
            href={href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "flex items-center gap-3 rounded-xl text-sm font-medium transition-colors",
              compact ? "flex-col gap-1 px-3 py-2 text-[11px]" : "px-3 py-2.5",
              active ? "bg-brand/12 text-brand" : "text-muted hover:bg-surface-2 hover:text-text",
            )}
          >
            <Icon className="h-[18px] w-[18px]" aria-hidden />
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
