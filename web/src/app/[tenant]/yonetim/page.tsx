"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Download } from "lucide-react";
import { PeriodFilter, RankBars, RevenueChart } from "@/components/charts/charts";
import { PageHeader, StatCard } from "@/components/panel/panel-shell";
import { buttonVariants } from "@/components/ui/button";
import { apiFetch } from "@/lib/api/client";
import { formatMoneyAxis, formatPrice } from "@/lib/format";

type SalesReport = {
  period: { from: string; to: string; days: number };
  totals: {
    revenue: number;
    orders: number;
    average_order: number;
    discounts: number;
    platform_fee: number;
    net: number;
    active_subscriptions: number;
    mrr: number;
  };
  daily: { date: string; revenue: number; orders: number }[];
  top_courses: { course: string; revenue: number; orders: number }[];
};

export default function BrandOverview() {
  const [days, setDays] = useState(30);
  const { data } = useQuery({
    queryKey: ["brand", "reports", "sales", days],
    queryFn: async () => (await apiFetch<{ data: SalesReport }>(`/brand/reports/sales?days=${days}`)).data,
  });

  const t = data?.totals;

  return (
    <>
      <PageHeader
        title="Satış analitiği"
        description={data ? `${data.period.from} – ${data.period.to}` : "Yükleniyor…"}
        action={
          <div className="flex items-center gap-2">
            <PeriodFilter value={days} onChange={setDays} />
            <a href={`/api/brand/reports/sales?days=${days}&format=csv`} className={buttonVariants({ variant: "outline" })}>
              <Download /> CSV
            </a>
          </div>
        }
      />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Ciro" value={t ? formatPrice(t.revenue) : "…"} hint={t ? `${t.orders} ödenmiş sipariş` : undefined} />
        <StatCard label="Net gelir" value={t ? formatPrice(t.net) : "…"} hint={t ? `Komisyon ${formatPrice(t.platform_fee)}` : undefined} />
        <StatCard label="Ortalama sepet" value={t ? formatPrice(t.average_order) : "…"} hint={t ? `İndirim ${formatPrice(t.discounts)}` : undefined} />
        <StatCard label="Aktif abone" value={t?.active_subscriptions ?? "…"} hint={t ? `Aylık ${formatMoneyAxis(t.mrr)} yinelenen gelir` : undefined} />
      </div>

      <section className="mt-6 rounded-xl border bg-card p-5">
        <h2 className="mb-4 font-semibold">Günlük ciro</h2>
        {data && <RevenueChart data={data.daily} />}
      </section>

      <section className="mt-6 rounded-xl border bg-card p-5">
        <h2 className="mb-4 font-semibold">En çok satan kurslar</h2>
        {data?.top_courses.length ? (
          <RankBars data={data.top_courses.map((c) => ({ name: c.course, value: c.revenue }))} format={formatPrice} axisFormat={formatMoneyAxis} />
        ) : (
          <p className="text-sm text-muted-foreground">Bu dönemde satış yok.</p>
        )}
      </section>
    </>
  );
}
