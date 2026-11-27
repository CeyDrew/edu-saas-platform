"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Field } from "@/components/auth/login-form";
import { PageHeader } from "@/components/panel/panel-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ApiError, apiFetch, json } from "@/lib/api/client";

type Payout = { account_holder: string; iban: string; vkn: string } | null;

export default function PayoutPage() {
  const { data, isLoading } = useQuery({ queryKey: ["brand", "payout"], queryFn: async () => (await apiFetch<{ data: Payout }>("/brand/payout")).data });

  if (isLoading) return <p className="text-muted-foreground">Yükleniyor…</p>;

  return <PayoutForm initial={data ?? null} />;
}

function PayoutForm({ initial }: { initial: Payout }) {
  const [form, setForm] = useState(initial ?? { account_holder: "", iban: "", vkn: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const save = useMutation({
    mutationFn: () => apiFetch<{ data: Payout }>("/brand/payout", { method: "PUT", body: json(form) }),
    onSuccess: ({ data: saved }) => {
      setErrors({});
      if (saved) setForm(saved);
      toast.success("Ödeme bilgileri kaydedildi");
    },
    onError: (error) => setErrors(error instanceof ApiError ? error.fieldErrors() : {}),
  });

  return (
    <>
      <PageHeader title="Ödeme bilgileri" description="Satış gelirinin platform komisyonu düşüldükten sonra aktarılacağı hesap." />
      <form
        className="max-w-lg space-y-4 rounded-xl border bg-card p-6"
        onSubmit={(event) => {
          event.preventDefault();
          save.mutate();
        }}
      >
        <Field label="Hesap sahibi" error={errors.account_holder}>
          <Input value={form.account_holder} onChange={(e) => setForm({ ...form, account_holder: e.target.value })} />
        </Field>
        <Field label="IBAN" error={errors.iban}>
          <Input value={form.iban} placeholder="TR00 0000 0000 0000 0000 0000 00" onChange={(e) => setForm({ ...form, iban: e.target.value })} />
        </Field>
        <Field label="Vergi no (VKN)" error={errors.vkn}>
          <Input value={form.vkn} inputMode="numeric" maxLength={10} onChange={(e) => setForm({ ...form, vkn: e.target.value })} />
        </Field>
        <Button type="submit" disabled={save.isPending}>
          Kaydet
        </Button>
      </form>
    </>
  );
}
