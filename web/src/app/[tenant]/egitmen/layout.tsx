"use client";

import { BookOpen, Sparkles } from "lucide-react";
import { PanelShell } from "@/components/panel/panel-shell";

export default function InstructorLayout({ children }: { children: React.ReactNode }) {
  return (
    <PanelShell
      title="Eğitmen paneli"
      roles={["instructor", "brand-admin"]}
      nav={[
        { href: "/egitmen", label: "Kurslarım", icon: BookOpen, exact: true },
        { href: "/egitmen/ai-sorular", label: "AI soru onayı", icon: Sparkles },
      ]}
    >
      {children}
    </PanelShell>
  );
}
