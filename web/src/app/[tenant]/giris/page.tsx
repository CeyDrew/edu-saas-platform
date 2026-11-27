import type { Metadata } from "next";
import { Suspense } from "react";
import { AuthCard } from "@/components/auth/auth-card";
import { LoginForm } from "@/components/auth/login-form";

export const metadata: Metadata = { title: "Giriş yap" };

export default async function LoginPage({ params }: PageProps<"/[tenant]/giris">) {
  const { tenant } = await params;

  return (
    <AuthCard title="Tekrar hoş geldin" subtitle="Kaldığın yerden devam et." footer={{ text: "Hesabın yok mu?", href: "/kayit", label: "Kaydol" }}>
      <Suspense>
        <LoginForm demoHint={`Demo: ogrenci@${tenant}.test, egitmen@${tenant}.test veya yonetici@${tenant}.test, şifre: password`} />
      </Suspense>
    </AuthCard>
  );
}
