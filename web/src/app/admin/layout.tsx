"use client";

import { usePathname } from "next/navigation";
import { Building2 } from "lucide-react";
import { PanelShell } from "@/components/panel/panel-shell";

export default function PlatformAdminLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  if (pathname === "/admin/giris") return <>{children}</>;

  return (
    <PanelShell
      title="Platform"
      roles={["platform-admin"]}
      loginPath="/admin/giris"
      nav={[{ href: "/admin", label: "Markalar", icon: Building2, exact: true }]}
    >
      {children}
    </PanelShell>
  );
}
