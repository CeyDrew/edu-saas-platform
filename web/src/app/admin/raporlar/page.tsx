"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { Download } from "lucide-react";
import { PeriodFilter, RankBars } from "@/components/charts/charts";
import { PageHeader, StatCard } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiFetch } from "@/lib/api/client";
import { formatMoneyAxis, formatPrice } from "@/lib/format";

type PlatformReport = {
  brands: { slug: string; name: string; is_active: boolean; revenue: number; platform_fee: number; orders: number; students: number }[];
  totals: { revenue: number; platform_fee: number; orders: number; students: number };
};

export default function PlatformReports() {
  const [days, setDays] = useState(30);
  const { data } = useQuery({
    queryKey: ["platform", "reports", days],
    queryFn: async () => (await apiFetch<{ data: PlatformReport }>(`/platform/reports?days=${days}`)).data,
  });

  return (
    <>
      <PageHeader
        title="Platform raporu"
        description="Markaların cirosu ve platform komisyonu."
        action={
          <div className="flex items-center gap-2">
            <PeriodFilter value={days} onChange={setDays} />
            <a href={`/api/platform/reports?days=${days}&format=csv`} className={buttonVariants({ variant: "outline" })}>
              <Download /> CSV
            </a>
          </div>
        }
      />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Toplam ciro" value={data ? formatPrice(data.totals.revenue) : "…"} />
        <StatCard label="Platform komisyonu" value={data ? formatPrice(data.totals.platform_fee) : "…"} />
        <StatCard label="Sipariş" value={data?.totals.orders ?? "…"} />
        <StatCard label="Öğrenci" value={data?.totals.students ?? "…"} />
      </div>
      <section className="mt-6 rounded-xl border bg-card p-5">
        <h2 className="mb-4 font-semibold">Markaya göre komisyon</h2>
        {data && <RankBars data={data.brands.map((b) => ({ name: b.name, value: b.platform_fee }))} format={formatPrice} axisFormat={formatMoneyAxis} />}
      </section>
      <section className="mt-6 rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Marka</TableHead>
              <TableHead className="text-right">Sipariş</TableHead>
              <TableHead className="text-right">Ciro</TableHead>
              <TableHead className="text-right">Komisyon</TableHead>
              <TableHead className="text-right">Öğrenci</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.brands.map((brand) => (
              <TableRow key={brand.slug}>
                <TableCell>
                  {brand.name} {!brand.is_active && <Badge variant="outline">Kapalı</Badge>}
                </TableCell>
                <TableCell className="text-right">{brand.orders}</TableCell>
                <TableCell className="text-right">{formatPrice(brand.revenue)}</TableCell>
                <TableCell className="text-right">{formatPrice(brand.platform_fee)}</TableCell>
                <TableCell className="text-right">{brand.students}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </section>
    </>
  );
}
