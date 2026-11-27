"use client";

import { BarChart3, BookOpen, Users } from "lucide-react";
import { PanelShell } from "@/components/panel/panel-shell";

export default function BrandAdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <PanelShell
      title="Marka yönetimi"
      roles={["brand-admin"]}
      nav={[
        { href: "/yonetim", label: "Genel bakış", icon: BarChart3, exact: true },
        { href: "/yonetim/uyeler", label: "Üyeler", icon: Users },
        { href: "/egitmen", label: "Kurslar", icon: BookOpen },
      ]}
    >
      {children}
    </PanelShell>
  );
}
