import { LogOut } from "lucide-react";
import { logoutAction } from "@/lib/auth-actions";
import { getCurrentUser } from "@/lib/session";

export async function UserMenu() {
  const user = await getCurrentUser();
  const initials = `${user.first_name?.[0] ?? ""}${user.last_name?.[0] ?? ""}`.toUpperCase() || "C";

  return (
    <div className="flex items-center gap-3">
      <div className="hidden text-right sm:block">
        <p className="text-sm font-medium">{[user.first_name, user.last_name].filter(Boolean).join(" ") || user.email}</p>
        <p className="text-xs text-muted">{user.email}</p>
      </div>
      <span className="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand to-violet text-sm font-semibold text-ink">{initials}</span>
      <form action={logoutAction}>
        <button type="submit" title="Se déconnecter" className="grid h-10 w-10 place-items-center rounded-xl text-muted hover:bg-surface-2 hover:text-text">
          <LogOut className="h-[18px] w-[18px]" />
          <span className="sr-only">Se déconnecter</span>
        </button>
      </form>
    </div>
  );
}

export function UserMenuSkeleton() {
  return <div className="h-10 w-40 animate-pulse rounded-xl bg-surface-2" />;
}
