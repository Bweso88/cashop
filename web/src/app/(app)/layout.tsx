import { Suspense } from "react";
import { Logo } from "@/components/brand/Logo";
import { NavLinks, NavList } from "@/components/app/NavLinks";
import { UserMenu, UserMenuSkeleton } from "@/components/app/UserMenu";

/**
 * Cadre de l'espace client. La navigation est statique (pré-rendue) ; seul ce qui dépend de la
 * session est lu côté serveur, derrière des <Suspense>.
 */
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-dvh lg:grid lg:grid-cols-[260px_1fr]">
      <aside className="sticky top-0 hidden h-dvh flex-col border-r border-line bg-surface/60 p-5 backdrop-blur lg:flex">
        <Logo className="mb-8 px-2" />
        <Suspense fallback={<NavList />}>
          <NavLinks />
        </Suspense>
        <p className="mt-auto px-2 text-xs text-faint">Cashop · environnement de démonstration</p>
      </aside>
      <div className="flex min-h-dvh min-w-0 flex-col">
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between gap-4 border-b border-line bg-ink/80 px-4 backdrop-blur sm:px-8">
          <div className="lg:hidden"><Logo /></div>
          <div className="ml-auto">
            <Suspense fallback={<UserMenuSkeleton />}>
              <UserMenu />
            </Suspense>
          </div>
        </header>
        <main className="flex-1 px-4 pb-28 pt-6 sm:px-8 lg:pb-10">{children}</main>
        <div className="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-surface/95 px-2 py-1.5 backdrop-blur lg:hidden">
          <Suspense fallback={<NavList compact />}>
            <NavLinks compact />
          </Suspense>
        </div>
      </div>
    </div>
  );
}
