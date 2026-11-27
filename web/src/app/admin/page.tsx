"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { Plus } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Paginated, PlatformTenant } from "@/lib/api/types";

export default function PlatformTenants() {
  const client = useQueryClient();
  const { data } = useQuery({
    queryKey: ["platform", "tenants"],
    queryFn: () => apiFetch<Paginated<PlatformTenant>>("/platform/tenants"),
  });

  const toggle = useMutation({
    mutationFn: (tenant: PlatformTenant) =>
      apiFetch(`/platform/tenants/${tenant.slug}`, { method: "PATCH", body: json({ is_active: !tenant.is_active }) }),
    onSuccess: () => client.invalidateQueries({ queryKey: ["platform", "tenants"] }),
  });

  return (
    <>
      <PageHeader title="Markalar" description="Marka aç, kapat, plan ve komisyon oranını gör." action={<NewTenantDialog />} />
      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Marka</TableHead>
              <TableHead>Plan</TableHead>
              <TableHead className="text-right">Komisyon</TableHead>
              <TableHead className="text-right">Kullanıcı</TableHead>
              <TableHead className="text-right">Aktif</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.data.map((tenant) => (
              <TableRow key={tenant.id}>
                <TableCell>
                  <div className="flex items-center gap-3">
                    <span
                      className="size-6 rounded-md"
                      style={{ background: `linear-gradient(135deg, ${tenant.theme.primary}, ${tenant.theme.accent})` }}
                    />
                    <div>
                      <p className="font-medium">{tenant.name}</p>
                      <p className="font-mono text-xs text-muted-foreground">{tenant.slug}</p>
                    </div>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="secondary">{tenant.plan}</Badge>
                </TableCell>
                <TableCell className="text-right">%{tenant.commission_rate}</TableCell>
                <TableCell className="text-right">{tenant.users_count}</TableCell>
                <TableCell className="text-right">
                  <Switch checked={tenant.is_active} onCheckedChange={() => toggle.mutate(tenant)} aria-label="Aktif" />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}

function NewTenantDialog() {
  const client = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", slug: "", tagline: "", primary: "#2563eb", accent: "#f97316" });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const create = useMutation({
    mutationFn: () =>
      apiFetch("/platform/tenants", {
        method: "POST",
        body: json({
          name: form.name,
          slug: form.slug,
          tagline: form.tagline,
          theme: { primary: form.primary, accent: form.accent, font: "sans", hero: "gradient" },
        }),
      }),
    onSuccess: () => {
      toast.success("Marka açıldı");
      setOpen(false);
      client.invalidateQueries({ queryKey: ["platform", "tenants"] });
    },
    onError: (error) => setErrors(error instanceof ApiError ? error.fieldErrors() : {}),
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button />}>
        <Plus /> Yeni marka
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Yeni marka</DialogTitle>
        </DialogHeader>
        <form
          className="space-y-3"
          onSubmit={(event) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          {(
            [
              ["name", "Marka adı"],
              ["slug", "Alt alan adı"],
              ["tagline", "Slogan"],
            ] as const
          ).map(([field, label]) => (
            <div key={field} className="space-y-1.5">
              <Label htmlFor={field}>{label}</Label>
              <Input id={field} value={form[field]} onChange={(e) => setForm({ ...form, [field]: e.target.value })} />
              {errors[field] && <p className="text-sm text-destructive">{errors[field]}</p>}
            </div>
          ))}
          <div className="grid grid-cols-2 gap-3">
            {(
              [
                ["primary", "Ana renk"],
                ["accent", "Vurgu rengi"],
              ] as const
            ).map(([field, label]) => (
              <div key={field} className="space-y-1.5">
                <Label htmlFor={field}>{label}</Label>
                <Input id={field} type="color" value={form[field]} onChange={(e) => setForm({ ...form, [field]: e.target.value })} />
              </div>
            ))}
          </div>
          <Button type="submit" className="w-full" disabled={create.isPending}>
            Markayı aç
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}
