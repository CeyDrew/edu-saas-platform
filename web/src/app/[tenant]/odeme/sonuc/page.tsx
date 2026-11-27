"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";
import { CheckCircle2, Clock, XCircle } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api/client";
import { formatPrice } from "@/lib/format";

type Order = { number: string; status: "pending" | "paid" | "failed"; total: number; items: { description: string }[] };

function Result() {
  const number = useSearchParams().get("siparis");
  const { data: order } = useQuery({
    queryKey: ["order", number],
    enabled: !!number,
    queryFn: async () => (await apiFetch<{ data: Order }>(`/me/orders/${number}`)).data,
    // The webhook may land a moment after the redirect.
    refetchInterval: (query) => (query.state.data?.status === "pending" ? 1500 : false),
  });

  if (!order) return <p className="text-center text-muted-foreground">Sipariş yükleniyor…</p>;

  const view = {
    paid: { icon: CheckCircle2, color: "text-emerald-600", title: "Ödeme başarılı", text: "Kursun hesabına eklendi. İyi çalışmalar!" },
    pending: { icon: Clock, color: "text-amber-500", title: "Ödeme bekleniyor", text: "Ödeme kuruluşundan onay bekleniyor…" },
    failed: { icon: XCircle, color: "text-destructive", title: "Ödeme alınamadı", text: "Kartın reddedildi. Tekrar deneyebilirsin." },
  }[order.status];
  const Icon = view.icon;

  return (
    <div className="space-y-4 text-center" data-testid="order-result" data-status={order.status}>
      <Icon className={`mx-auto size-14 ${view.color}`} />
      <h1 className="font-heading text-2xl font-bold">{view.title}</h1>
      <p className="text-muted-foreground">{view.text}</p>
      <p className="text-sm">
        {order.items.map((item) => item.description).join(", ")} · <strong>{formatPrice(order.total)}</strong>
      </p>
      <p className="font-mono text-xs text-muted-foreground">Sipariş no: {order.number}</p>
      <Link href={order.status === "paid" ? "/panel" : "/kurslar"} className={buttonVariants({ size: "lg" })}>
        {order.status === "paid" ? "Kurslarıma git" : "Kurslara dön"}
      </Link>
    </div>
  );
}

export default function OrderResultPage() {
  return (
    <div className="mx-auto max-w-lg px-4 py-16">
      <Suspense>
        <Result />
      </Suspense>
    </div>
  );
}
