"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { PageHeader } from "@/components/panel/panel-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import { formatDate, formatPrice } from "@/lib/format";

type Coupon = {
  id: number;
  code: string;
  type: "percent" | "fixed";
  value: number;
  max_uses: number | null;
  used_count: number;
  expires_at: string | null;
  is_active: boolean;
};

export default function CouponsPage() {
  const client = useQueryClient();
  const { data } = useQuery({ queryKey: ["brand", "coupons"], queryFn: async () => (await apiFetch<{ data: Coupon[] }>("/brand/coupons")).data });
  const [form, setForm] = useState({ code: "", type: "percent", value: "20", max_uses: "" });
  const refresh = () => client.invalidateQueries({ queryKey: ["brand", "coupons"] });

  const create = useMutation({
    mutationFn: () =>
      apiFetch("/brand/coupons", {
        method: "POST",
        body: json({
          code: form.code,
          type: form.type,
          value: form.type === "fixed" ? Math.round(Number(form.value) * 100) : Number(form.value),
          max_uses: form.max_uses ? Number(form.max_uses) : null,
        }),
      }),
    onSuccess: () => {
      toast.success("Kupon oluşturuldu");
      setForm({ ...form, code: "" });
      refresh();
    },
    onError: (error) => toast.error(error instanceof ApiError ? (Object.values(error.fieldErrors())[0] ?? error.message) : "Hata"),
  });

  const toggle = useMutation({
    mutationFn: (coupon: Coupon) => apiFetch(`/brand/coupons/${coupon.id}`, { method: "PATCH", body: json({ is_active: !coupon.is_active }) }),
    onSuccess: refresh,
  });

  return (
    <>
      <PageHeader title="Kuponlar" description="Yüzde veya sabit tutarlı indirim kodları." />
      <form
        className="mb-6 grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-5 sm:items-end"
        onSubmit={(event) => {
          event.preventDefault();
          create.mutate();
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="code">Kod</Label>
          <Input id="code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} required />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="type">Tür</Label>
          <select id="type" className="h-8 w-full rounded-lg border bg-background px-2 text-sm" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
            <option value="percent">Yüzde</option>
            <option value="fixed">Sabit (TL)</option>
          </select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="value">Değer</Label>
          <Input id="value" type="number" min={1} value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="max">Kullanım limiti</Label>
          <Input id="max" type="number" min={1} placeholder="Sınırsız" value={form.max_uses} onChange={(e) => setForm({ ...form, max_uses: e.target.value })} />
        </div>
        <Button type="submit" disabled={create.isPending}>
          Oluştur
        </Button>
      </form>
      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Kod</TableHead>
              <TableHead>İndirim</TableHead>
              <TableHead>Kullanım</TableHead>
              <TableHead>Bitiş</TableHead>
              <TableHead className="text-right">Aktif</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.map((coupon) => (
              <TableRow key={coupon.id}>
                <TableCell className="font-mono font-medium">{coupon.code}</TableCell>
                <TableCell>{coupon.type === "percent" ? `%${coupon.value}` : formatPrice(coupon.value)}</TableCell>
                <TableCell>
                  {coupon.used_count}
                  {coupon.max_uses ? ` / ${coupon.max_uses}` : ""}
                </TableCell>
                <TableCell>{coupon.expires_at ? formatDate(coupon.expires_at) : "-"}</TableCell>
                <TableCell className="text-right">
                  <Switch checked={coupon.is_active} onCheckedChange={() => toggle.mutate(coupon)} aria-label="Aktif" />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}
