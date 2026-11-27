"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { UserPlus } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Paginated, RoleName, User } from "@/lib/api/types";
import { cn } from "@/lib/utils";

const roleLabel: Record<RoleName, string> = {
  "platform-admin": "Platform",
  "brand-admin": "Yönetici",
  instructor: "Eğitmen",
  student: "Öğrenci",
};

export default function MembersPage() {
  const [role, setRole] = useState<string>("");
  const [search, setSearch] = useState("");
  const params = new URLSearchParams({ ...(role && { role }), ...(search && { search }) });

  const { data } = useQuery({
    queryKey: ["brand", "members", role, search],
    queryFn: () => apiFetch<Paginated<User>>(`/brand/members?${params}`),
  });

  return (
    <>
      <PageHeader title="Üyeler" description={`${data?.meta.total ?? "…"} kişi`} action={<AddMemberDialog />} />
      <div className="mb-4 flex flex-wrap items-center gap-2">
        {["", "brand-admin", "instructor", "student"].map((value) => (
          <button
            key={value}
            onClick={() => setRole(value)}
            className={cn(
              "rounded-full border px-3 py-1 text-sm",
              role === value ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted",
            )}
          >
            {value ? roleLabel[value as RoleName] : "Tümü"}
          </button>
        ))}
        <Input placeholder="İsim veya e-posta ara" className="ml-auto max-w-xs" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>
      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Ad</TableHead>
              <TableHead>E-posta</TableHead>
              <TableHead>Telefon</TableHead>
              <TableHead>Rol</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.data.map((member) => (
              <TableRow key={member.id}>
                <TableCell className="font-medium">{member.name}</TableCell>
                <TableCell>{member.email}</TableCell>
                <TableCell>{member.phone ?? "-"}</TableCell>
                <TableCell className="space-x-1">
                  {member.roles.map((r) => (
                    <Badge key={r} variant={r === "student" ? "secondary" : "default"}>
                      {roleLabel[r]}
                    </Badge>
                  ))}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}

function AddMemberDialog() {
  const client = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", email: "", phone: "", password: "", role: "instructor" });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const create = useMutation({
    mutationFn: () => apiFetch("/brand/members", { method: "POST", body: json({ ...form, phone: form.phone || null }) }),
    onSuccess: () => {
      toast.success("Üye eklendi");
      setOpen(false);
      setErrors({});
      client.invalidateQueries({ queryKey: ["brand", "members"] });
    },
    onError: (error) => setErrors(error instanceof ApiError ? error.fieldErrors() : {}),
  });

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button />}>
        <UserPlus /> Üye ekle
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Üye ekle</DialogTitle>
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
              ["name", "Ad soyad", "text"],
              ["email", "E-posta", "email"],
              ["phone", "Cep telefonu", "tel"],
              ["password", "Geçici şifre", "password"],
            ] as const
          ).map(([field, label, type]) => (
            <div key={field} className="space-y-1.5">
              <Label htmlFor={field}>{label}</Label>
              <Input id={field} type={type} value={form[field]} onChange={(e) => setForm({ ...form, [field]: e.target.value })} />
              {errors[field] && <p className="text-sm text-destructive">{errors[field]}</p>}
            </div>
          ))}
          <div className="space-y-1.5">
            <Label htmlFor="role">Rol</Label>
            <select
              id="role"
              className="h-8 w-full rounded-lg border bg-background px-2 text-sm"
              value={form.role}
              onChange={(e) => setForm({ ...form, role: e.target.value })}
            >
              <option value="instructor">Eğitmen</option>
              <option value="brand-admin">Marka yöneticisi</option>
              <option value="student">Öğrenci</option>
            </select>
          </div>
          <Button type="submit" className="w-full" disabled={create.isPending}>
            Ekle
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}
