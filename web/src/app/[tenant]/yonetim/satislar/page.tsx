"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiFetch } from "@/lib/api/client";
import type { Paginated } from "@/lib/api/types";
import { formatDate, formatPrice } from "@/lib/format";
import { cn } from "@/lib/utils";

type Order = {
  number: string;
  type: string;
  status: string;
  total: number;
  discount: number;
  created_at: string;
  paid_at: string | null;
  items: { description: string }[];
  customer: { name: string; email: string };
  invoice: { number: string; kind: string } | null;
};

const statuses = [
  ["", "Tümü"],
  ["paid", "Ödendi"],
  ["pending", "Bekliyor"],
  ["failed", "Başarısız"],
] as const;

export default function SalesPage() {
  const [status, setStatus] = useState("");
  const { data } = useQuery({
    queryKey: ["brand", "orders", status],
    queryFn: () => apiFetch<Paginated<Order>>(`/brand/orders${status ? `?status=${status}` : ""}`),
  });

  return (
    <>
      <PageHeader title="Siparişler" description={`${data?.meta.total ?? "…"} sipariş`} />
      <div className="mb-4 flex gap-2">
        {statuses.map(([value, label]) => (
          <button
            key={value}
            onClick={() => setStatus(value)}
            className={cn(
              "rounded-full border px-3 py-1 text-sm",
              status === value ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted",
            )}
          >
            {label}
          </button>
        ))}
      </div>
      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Sipariş</TableHead>
              <TableHead>Müşteri</TableHead>
              <TableHead>Ürün</TableHead>
              <TableHead>Durum</TableHead>
              <TableHead>Fatura</TableHead>
              <TableHead className="text-right">Tutar</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.data.map((order) => (
              <TableRow key={order.number}>
                <TableCell>
                  <p className="font-mono text-xs">{order.number}</p>
                  <p className="text-xs text-muted-foreground">{formatDate(order.paid_at ?? order.created_at)}</p>
                </TableCell>
                <TableCell>
                  <p>{order.customer.name}</p>
                  <p className="text-xs text-muted-foreground">{order.customer.email}</p>
                </TableCell>
                <TableCell className="max-w-56 truncate">{order.items.map((item) => item.description).join(", ")}</TableCell>
                <TableCell>
                  <Badge variant={order.status === "paid" ? "secondary" : "outline"}>{order.status}</Badge>
                </TableCell>
                <TableCell className="text-xs">{order.invoice?.number ?? "-"}</TableCell>
                <TableCell className="text-right">
                  {formatPrice(order.total)}
                  {order.discount > 0 && <p className="text-xs text-muted-foreground">-{formatPrice(order.discount)}</p>}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}
