"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import type { LucideIcon } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { hasRole, useMe } from "@/hooks/use-me";
import type { RoleName } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export type NavItem = { href: string; label: string; icon: LucideIcon; exact?: boolean };

/**
 * Client-side shell for the dashboards. The API enforces access; this only routes
 * guests to the login page and users without the role back to their own panel.
 */
export function PanelShell({
  title,
  roles,
  nav,
  children,
  loginPath = "/giris",
}: {
  title: string;
  roles: RoleName[];
  nav: NavItem[];
  children: React.ReactNode;
  loginPath?: string;
}) {
  const { data: me, isLoading } = useMe();
  const router = useRouter();
  const pathname = usePathname();
  const allowed = hasRole(me, ...roles);

  useEffect(() => {
    if (isLoading) return;
    if (!me) router.replace(`${loginPath}?next=${encodeURIComponent(pathname)}`);
    else if (!allowed) router.replace("/");
  }, [isLoading, me, allowed, router, pathname, loginPath]);

  if (isLoading || !allowed) {
    return (
      <div className="mx-auto max-w-6xl space-y-4 px-4 py-10">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  return (
    <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 md:grid-cols-[220px_1fr]">
      <aside className="space-y-1">
        <p className="px-3 pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">{title}</p>
        {nav.map(({ href, label, icon: Icon, exact }) => {
          const active = exact ? pathname === href : pathname.startsWith(href);
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                "flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition",
                active ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-muted hover:text-foreground",
              )}
            >
              <Icon className="size-4" /> {label}
            </Link>
          );
        })}
      </aside>
      <section className="min-w-0">{children}</section>
    </div>
  );
}

export function PageHeader({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="font-heading text-2xl font-bold">{title}</h1>
        {description && <p className="text-sm text-muted-foreground">{description}</p>}
      </div>
      {action}
    </div>
  );
}

export function StatCard({ label, value, hint }: { label: string; value: React.ReactNode; hint?: string }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 font-heading text-2xl font-bold">{value}</p>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}
