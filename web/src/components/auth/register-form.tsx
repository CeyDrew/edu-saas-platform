"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { Field } from "@/components/auth/login-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { User } from "@/lib/api/types";

const schema = z
  .object({
    name: z.string().min(2, "Adınızı girin."),
    email: z.email("Geçerli bir e-posta girin."),
    phone: z.string().optional(),
    password: z.string().min(8, "En az 8 karakter."),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ["password_confirmation"],
    message: "Şifreler eşleşmiyor.",
  });

type Values = z.infer<typeof schema>;

export function RegisterForm() {
  const router = useRouter();
  const client = useQueryClient();
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", email: "", phone: "", password: "", password_confirmation: "" },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const { data } = await apiFetch<{ data: User }>("/auth/register", {
        method: "POST",
        body: json({ ...values, phone: values.phone || null }),
      });
      client.setQueryData(["me"], data);
      router.push("/panel");
      router.refresh();
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, message] of Object.entries(error.fieldErrors())) {
          form.setError(field as keyof Values, { message });
        }
      }
    }
  });

  const errors = form.formState.errors;

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <Field label="Ad soyad" error={errors.name?.message}>
        <Input autoComplete="name" {...form.register("name")} />
      </Field>
      <Field label="E-posta" error={errors.email?.message}>
        <Input type="email" autoComplete="email" {...form.register("email")} />
      </Field>
      <Field label="Cep telefonu (isteğe bağlı)" error={errors.phone?.message}>
        <Input type="tel" placeholder="0532 123 45 67" {...form.register("phone")} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Şifre" error={errors.password?.message}>
          <Input type="password" autoComplete="new-password" {...form.register("password")} />
        </Field>
        <Field label="Şifre tekrar" error={errors.password_confirmation?.message}>
          <Input type="password" autoComplete="new-password" {...form.register("password_confirmation")} />
        </Field>
      </div>
      <Button type="submit" className="h-10 w-full" disabled={form.formState.isSubmitting}>
        Hesap oluştur
      </Button>
    </form>
  );
}
