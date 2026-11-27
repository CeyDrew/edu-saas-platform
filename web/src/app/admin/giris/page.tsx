import type { Metadata } from "next";
import { Suspense } from "react";
import { AuthCard } from "@/components/auth/auth-card";
import { LoginForm } from "@/components/auth/login-form";

export const metadata: Metadata = { title: "Platform girişi" };

export default function PlatformLoginPage() {
  return (
    <AuthCard title="Platform girişi" subtitle="Sadece platform yöneticileri.">
      <Suspense>
        <LoginForm demoHint="Demo: admin@edusaas.test, şifre: password" />
      </Suspense>
    </AuthCard>
  );
}
