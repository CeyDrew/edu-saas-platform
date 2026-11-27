"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Lock, Tag } from "lucide-react";
import { Field } from "@/components/auth/login-form";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import { formatPrice } from "@/lib/format";
import { cn } from "@/lib/utils";

type Quote = { description: string; subtotal: number; discount: number; total: number; coupon: string | null };
type CheckoutResponse = { data: { order: { number: string; status: string }; payment_page_url: string | null } };

export function CheckoutForm({ product }: { product: { course?: string; plan_id?: number } }) {
  const router = useRouter();
  const [coupon, setCoupon] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [type, setType] = useState<"individual" | "corporate">("individual");
  const [billing, setBilling] = useState({ name: "", tckn: "", company: "", vkn: "", tax_office: "", address: "", city: "İstanbul" });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const quote = useQuery({
    queryKey: ["quote", product, appliedCoupon],
    queryFn: async () =>
      (await apiFetch<{ data: Quote }>("/checkout/quote", { method: "POST", body: json({ ...product, coupon: appliedCoupon }) })).data,
  });

  const applyCoupon = async () => {
    setCouponError(null);
    try {
      await apiFetch("/checkout/quote", { method: "POST", body: json({ ...product, coupon }) });
      setAppliedCoupon(coupon);
    } catch (error) {
      setCouponError(error instanceof ApiError ? (error.fieldErrors().coupon ?? error.message) : "Hata");
    }
  };

  const pay = useMutation({
    mutationFn: () =>
      apiFetch<CheckoutResponse>("/checkout", {
        method: "POST",
        body: json({ ...product, coupon: appliedCoupon, billing: { type, ...billing } }),
      }),
    onSuccess: ({ data }) => {
      if (data.payment_page_url) window.location.assign(data.payment_page_url);
      else router.push(`/odeme/sonuc?siparis=${data.order.number}`);
    },
    onError: (error) => {
      if (!(error instanceof ApiError)) return;
      const fields = error.fieldErrors();
      setErrors(Object.fromEntries(Object.entries(fields).map(([key, value]) => [key.replace("billing.", ""), value])));
    },
  });

  const set = (field: keyof typeof billing) => (event: React.ChangeEvent<HTMLInputElement>) =>
    setBilling({ ...billing, [field]: event.target.value });

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
      <form
        className="space-y-6 rounded-2xl border bg-card p-6"
        onSubmit={(event) => {
          event.preventDefault();
          pay.mutate();
        }}
      >
        <div>
          <h2 className="font-heading text-lg font-semibold">Fatura bilgileri</h2>
          <p className="text-sm text-muted-foreground">TC kimlik ve vergi numarası kontrol haneleriyle doğrulanır.</p>
        </div>
        <div className="inline-flex rounded-lg border p-1 text-sm">
          {(
            [
              ["individual", "Bireysel"],
              ["corporate", "Kurumsal"],
            ] as const
          ).map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setType(value)}
              className={cn("rounded-md px-4 py-1.5", type === value ? "bg-primary text-primary-foreground" : "text-muted-foreground")}
            >
              {label}
            </button>
          ))}
        </div>

        {type === "individual" ? (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Ad soyad" error={errors.name}>
              <Input value={billing.name} onChange={set("name")} />
            </Field>
            <Field label="TC kimlik no" error={errors.tckn}>
              <Input inputMode="numeric" maxLength={11} value={billing.tckn} onChange={set("tckn")} placeholder="10000000146" />
            </Field>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Şirket ünvanı" error={errors.company}>
              <Input value={billing.company} onChange={set("company")} />
            </Field>
            <Field label="Vergi no (VKN)" error={errors.vkn}>
              <Input inputMode="numeric" maxLength={10} value={billing.vkn} onChange={set("vkn")} />
            </Field>
            <Field label="Vergi dairesi" error={errors.tax_office}>
              <Input value={billing.tax_office} onChange={set("tax_office")} />
            </Field>
          </div>
        )}
        <div className="grid gap-4 sm:grid-cols-[1fr_180px]">
          <Field label="Adres" error={errors.address}>
            <Input value={billing.address} onChange={set("address")} />
          </Field>
          <Field label="Şehir" error={errors.city}>
            <Input value={billing.city} onChange={set("city")} />
          </Field>
        </div>
        {(errors.course || errors.coupon) && <p className="text-sm text-destructive">{errors.course ?? errors.coupon}</p>}
        <Button type="submit" className="h-11 w-full" disabled={pay.isPending || !quote.data}>
          <Lock /> {quote.data?.total === 0 ? "Ücretsiz kaydol" : `Ödemeye geç · ${quote.data ? formatPrice(quote.data.total) : ""}`}
        </Button>
        <p className="text-center text-xs text-muted-foreground">
          Kart bilgileri ödeme kuruluşunun güvenli sayfasında girilir, sunucularımıza ulaşmaz.
        </p>
      </form>

      <aside className="h-fit space-y-4 rounded-2xl border bg-card p-6">
        <h2 className="font-heading font-semibold">Sipariş özeti</h2>
        {quote.data ? (
          <>
            <p className="text-sm">{quote.data.description}</p>
            <Separator />
            <dl className="space-y-1 text-sm">
              <div className="flex justify-between">
                <dt className="text-muted-foreground">Tutar</dt>
                <dd>{formatPrice(quote.data.subtotal)}</dd>
              </div>
              {quote.data.discount > 0 && (
                <div className="flex justify-between text-primary">
                  <dt>İndirim ({quote.data.coupon})</dt>
                  <dd>-{formatPrice(quote.data.discount)}</dd>
                </div>
              )}
              <div className="flex justify-between pt-2 text-base font-semibold">
                <dt>Toplam</dt>
                <dd>{formatPrice(quote.data.total)}</dd>
              </div>
            </dl>
          </>
        ) : (
          <p className="text-sm text-muted-foreground">{quote.error ? "Ürün bulunamadı." : "Hesaplanıyor…"}</p>
        )}
        <div className="flex gap-2">
          <Input placeholder="Kupon kodu" value={coupon} onChange={(e) => setCoupon(e.target.value.toUpperCase())} />
          <Button type="button" variant="outline" onClick={applyCoupon} disabled={!coupon}>
            <Tag /> Uygula
          </Button>
        </div>
        {couponError && <p className="text-sm text-destructive">{couponError}</p>}
      </aside>
    </div>
  );
}
