"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiFetch } from "@/lib/api/client";
import type { Paginated } from "@/lib/api/types";
import { formatDate, formatPrice } from "@/lib/format";

type Order = {
  number: string;
  status: string;
  total: number;
  created_at: string;
  items: { description: string }[];
  invoice: { number: string; kind: string } | null;
};
type Subscription = {
  plan: string;
  price: number;
  status: "active" | "past_due" | "suspended" | "cancelled";
  current_period_end: string;
  failed_attempts: number;
  grants_access: boolean;
} | null;

const orderStatus: Record<string, string> = { paid: "Ödendi", pending: "Bekliyor", failed: "Başarısız", refunded: "İade" };
const subscriptionStatus = { active: "Aktif", past_due: "Ödeme bekleniyor", suspended: "Askıda", cancelled: "İptal edildi" };

export default function OrdersPage() {
  const client = useQueryClient();
  const orders = useQuery({ queryKey: ["me", "orders"], queryFn: () => apiFetch<Paginated<Order>>("/me/orders") });
  const subscription = useQuery({
    queryKey: ["me", "subscription"],
    queryFn: async () => (await apiFetch<{ data: Subscription }>("/me/subscription")).data,
  });
  const cancel = useMutation({
    mutationFn: () => apiFetch("/me/subscription/cancel", { method: "POST" }),
    onSuccess: () => client.invalidateQueries({ queryKey: ["me"] }),
  });

  const sub = subscription.data;

  return (
    <>
      <PageHeader title="Siparişler ve abonelik" />
      <section className="mb-8 rounded-xl border bg-card p-5">
        <h2 className="mb-2 font-semibold">Abonelik</h2>
        {sub ? (
          <div className="flex flex-wrap items-center gap-4 text-sm">
            <span className="font-medium">{sub.plan}</span>
            <Badge variant={sub.grants_access ? "default" : "destructive"}>{subscriptionStatus[sub.status]}</Badge>
            <span className="text-muted-foreground">
              {sub.status === "cancelled" ? "Erişim bitişi" : "Sonraki yenileme"}: {formatDate(sub.current_period_end)}
            </span>
            {sub.failed_attempts > 0 && <span className="text-destructive">{sub.failed_attempts} başarısız deneme</span>}
            {sub.status === "active" && (
              <Button variant="outline" size="sm" className="ml-auto" onClick={() => confirm("Abonelik iptal edilsin mi?") && cancel.mutate()}>
                İptal et
              </Button>
            )}
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">
            Aboneliğin yok.{" "}
            <Link href="/abonelik" className={buttonVariants({ variant: "link", size: "sm" })}>
              Planları gör
            </Link>
          </p>
        )}
      </section>

      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Sipariş</TableHead>
              <TableHead>Ürün</TableHead>
              <TableHead>Durum</TableHead>
              <TableHead>Fatura</TableHead>
              <TableHead className="text-right">Tutar</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {orders.data?.data.map((order) => (
              <TableRow key={order.number}>
                <TableCell>
                  <p className="font-mono text-xs">{order.number}</p>
                  <p className="text-xs text-muted-foreground">{formatDate(order.created_at)}</p>
                </TableCell>
                <TableCell>{order.items.map((item) => item.description).join(", ")}</TableCell>
                <TableCell>
                  <Badge variant={order.status === "paid" ? "secondary" : "outline"}>{orderStatus[order.status] ?? order.status}</Badge>
                </TableCell>
                <TableCell className="text-xs">{order.invoice ? `${order.invoice.kind} ${order.invoice.number}` : "-"}</TableCell>
                <TableCell className="text-right">{formatPrice(order.total)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}
