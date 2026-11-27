import type { Metadata } from "next";
import { AuthCard } from "@/components/auth/auth-card";
import { RegisterForm } from "@/components/auth/register-form";

export const metadata: Metadata = { title: "Kaydol" };

export default function RegisterPage() {
  return (
    <AuthCard title="Hesap oluştur" subtitle="Ücretsiz kursları hemen izlemeye başla." footer={{ text: "Zaten üye misin?", href: "/giris", label: "Giriş yap" }}>
      <RegisterForm />
    </AuthCard>
  );
}
