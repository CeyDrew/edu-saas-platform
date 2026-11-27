"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { CreditCard, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ApiError, apiFetch, json } from "@/lib/api/client";

/**
 * Local stand-in for the payment provider's hosted page (used when no iyzico sandbox
 * keys are configured). Submitting sends a signed notification through the webhook path.
 */
function FakePaymentPage() {
  const token = useSearchParams().get("token") ?? "";
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const complete = async (success: boolean) => {
    setBusy(true);
    setError(null);
    try {
      const { data } = await apiFetch<{ data: { number: string } }>("/payments/fake/complete", {
        method: "POST",
        body: json({ token, success }),
      });
      router.push(`/odeme/sonuc?siparis=${data.number}`);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Ödeme tamamlanamadı.");
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-md px-4 py-12">
      <div className="space-y-6 rounded-2xl border bg-card p-6 shadow-sm">
        <div className="flex items-center justify-between">
          <h1 className="flex items-center gap-2 font-heading text-lg font-semibold">
            <CreditCard className="size-5" /> Güvenli ödeme
          </h1>
          <span className="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Test modu</span>
        </div>
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label>Kart üzerindeki isim</Label>
            <Input defaultValue="Test Kullanıcı" />
          </div>
          <div className="space-y-1.5">
            <Label>Kart numarası</Label>
            <Input defaultValue="5528 7900 0000 0008" inputMode="numeric" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>Son kullanma</Label>
              <Input defaultValue="12/30" />
            </div>
            <div className="space-y-1.5">
              <Label>CVC</Label>
              <Input defaultValue="123" />
            </div>
          </div>
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="space-y-2">
          <Button className="h-11 w-full" disabled={busy || !token} onClick={() => complete(true)}>
            <ShieldCheck /> Ödemeyi onayla
          </Button>
          <Button variant="outline" className="w-full" disabled={busy || !token} onClick={() => complete(false)}>
            Reddedilen kartı dene
          </Button>
        </div>
      </div>
    </div>
  );
}

export default function Page() {
  return (
    <Suspense>
      <FakePaymentPage />
    </Suspense>
  );
}
