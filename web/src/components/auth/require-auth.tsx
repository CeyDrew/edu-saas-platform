"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { useMe } from "@/hooks/use-me";

/** Renders children for signed-in users; sends guests to the login page and back. */
export function RequireAuth({ children }: { children: React.ReactNode }) {
  const { data: me, isLoading } = useMe();
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams().toString();

  useEffect(() => {
    if (!isLoading && !me) {
      const back = `${pathname}${search ? `?${search}` : ""}`;
      router.replace(`/giris?next=${encodeURIComponent(back)}`);
    }
  }, [isLoading, me, router, pathname, search]);

  if (!me) return <Skeleton className="h-96 w-full" />;

  return <>{children}</>;
}
