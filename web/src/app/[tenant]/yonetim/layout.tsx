"use client";

import { BarChart3, BookOpen, Landmark, Receipt, TicketPercent, Users } from "lucide-react";
import { PanelShell } from "@/components/panel/panel-shell";

export default function BrandAdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <PanelShell
      title="Marka yönetimi"
      roles={["brand-admin"]}
      nav={[
        { href: "/yonetim", label: "Satış analitiği", icon: BarChart3, exact: true },
        { href: "/yonetim/uyeler", label: "Üyeler", icon: Users },
        { href: "/egitmen", label: "Kurslar", icon: BookOpen },
        { href: "/yonetim/satislar", label: "Siparişler", icon: Receipt },
        { href: "/yonetim/kuponlar", label: "Kuponlar", icon: TicketPercent },
        { href: "/yonetim/odeme-bilgileri", label: "Ödeme bilgileri", icon: Landmark },
      ]}
    >
      {children}
    </PanelShell>
  );
}
