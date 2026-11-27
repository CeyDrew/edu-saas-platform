"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { GraduationCap, LogOut } from "lucide-react";
import { Button, buttonVariants } from "@/components/ui/button";
import { homeFor, useLogout, useMe } from "@/hooks/use-me";
import type { Tenant } from "@/lib/api/types";

export function SiteHeader({ tenant }: { tenant: Tenant }) {
  const { data: me, isLoading } = useMe();
  const logout = useLogout();
  const router = useRouter();

  return (
    <header className="sticky top-0 z-30 border-b bg-background/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4">
        <Link href="/" className="flex items-center gap-2 font-heading text-lg font-bold">
          <span className="brand-gradient flex size-8 items-center justify-center rounded-lg text-white">
            <GraduationCap className="size-5" />
          </span>
          {tenant.name}
        </Link>
        <nav className="hidden gap-4 text-sm text-muted-foreground sm:flex">
          <Link href="/kurslar" className="hover:text-foreground">
            Kurslar
          </Link>
          <Link href="/abonelik" className="hover:text-foreground">
            Abonelik
          </Link>
        </nav>
        <div className="ml-auto flex items-center gap-2">
          {isLoading ? null : me ? (
            <>
              <Link href={homeFor(me)} className={buttonVariants({ variant: "outline", size: "sm" })}>
                {me.name.split(" ")[0]} · Panelim
              </Link>
              <Button
                variant="ghost"
                size="icon-sm"
                aria-label="Çıkış yap"
                onClick={async () => {
                  await logout();
                  router.push("/");
                  router.refresh();
                }}
              >
                <LogOut />
              </Button>
            </>
          ) : (
            <>
              <Link href="/giris" className={buttonVariants({ variant: "ghost", size: "sm" })}>
                Giriş yap
              </Link>
              <Link href="/kayit" className={buttonVariants({ size: "sm" })}>
                Ücretsiz kaydol
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
