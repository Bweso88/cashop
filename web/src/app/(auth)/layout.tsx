import { Logo } from "@/components/brand/Logo";
import { formatMoney } from "@/lib/money";

/** Écrans publics : panneau de marque à gauche (grand écran), formulaire à droite. */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
      <aside className="relative hidden overflow-hidden border-r border-line bg-surface lg:flex lg:flex-col lg:justify-between lg:p-12">
        <Logo />
        <div className="relative mx-auto w-full max-w-md">
          <div className="absolute -inset-10 rounded-full bg-brand/10 blur-3xl" aria-hidden />
          <div className="card-gradient-brand relative rotate-[-4deg] rounded-3xl p-6 pb-14 shadow-2xl shadow-black/40">
            <p className="text-sm text-white/70">Portefeuille XAF</p>
            <p className="mt-6 text-3xl font-semibold text-white">{formatMoney(485_000, "XAF")}</p>
            <p className="mt-4 text-sm tracking-widest text-white/70">•••• 2606</p>
          </div>
          <div className="relative -mt-6 ml-16 rounded-2xl border border-line bg-surface-2/95 p-4 shadow-xl backdrop-blur">
            <div className="flex items-center justify-between gap-4">
              <div>
                <p className="text-sm font-semibold">{formatMoney(10_000, "EUR")} envoyés</p>
                <p className="text-xs text-muted">Reçus : {formatMoney(65_595, "XAF")} · Mobile Money</p>
              </div>
              <span className="rounded-full bg-brand/15 px-2.5 py-1 text-xs font-medium text-brand">Livré</span>
            </div>
          </div>
        </div>
        <div>
          <h2 className="text-3xl font-semibold leading-tight">
            Votre argent, <span className="text-brand">partout</span>
            <br />
            en quelques secondes.
          </h2>
          <p className="mt-3 max-w-md text-muted">
            Mobile Money, cartes et retrait en agence : comparez les offres et suivez chaque transfert en temps réel.
          </p>
        </div>
      </aside>
      <main className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-md">
          <div className="mb-8 lg:hidden">
            <Logo />
          </div>
          {children}
        </div>
      </main>
    </div>
  );
}
