"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { homeFor } from "@/hooks/use-me";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { User } from "@/lib/api/types";

const schema = z.object({
  email: z.email("Geçerli bir e-posta girin."),
  password: z.string().min(1, "Şifre gerekli."),
});

type Values = z.infer<typeof schema>;

export function LoginForm({ demoHint }: { demoHint?: string }) {
  const router = useRouter();
  const next = useSearchParams().get("next");
  const client = useQueryClient();
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { email: "", password: "" } });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const { data } = await apiFetch<{ data: User }>("/auth/login", { method: "POST", body: json(values) });
      client.setQueryData(["me"], data);
      router.push(next?.startsWith("/") ? next : homeFor(data));
      router.refresh();
    } catch (error) {
      const message = error instanceof ApiError ? (error.fieldErrors().email ?? error.message) : "Bağlantı hatası.";
      form.setError("email", { message });
    }
  });

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <Field label="E-posta" error={form.formState.errors.email?.message}>
        <Input type="email" autoComplete="email" {...form.register("email")} />
      </Field>
      <Field label="Şifre" error={form.formState.errors.password?.message}>
        <Input type="password" autoComplete="current-password" {...form.register("password")} />
      </Field>
      <Button type="submit" className="h-10 w-full" disabled={form.formState.isSubmitting}>
        {form.formState.isSubmitting ? "Giriş yapılıyor…" : "Giriş yap"}
      </Button>
      {demoHint && <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">{demoHint}</p>}
    </form>
  );
}

export function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
      {error && <p className="text-sm text-destructive">{error}</p>}
    </div>
  );
}
