"use client";

import { BookOpen, Receipt, Repeat } from "lucide-react";
import { PanelShell } from "@/components/panel/panel-shell";

export default function StudentLayout({ children }: { children: React.ReactNode }) {
  return (
    <PanelShell
      title="Öğrenci paneli"
      roles={["student", "instructor", "brand-admin"]}
      nav={[
        { href: "/panel", label: "Kurslarım", icon: BookOpen, exact: true },
        { href: "/panel/tekrar", label: "Tekrar listem", icon: Repeat },
        { href: "/panel/siparisler", label: "Siparişler ve abonelik", icon: Receipt },
      ]}
    >
      {children}
    </PanelShell>
  );
}
