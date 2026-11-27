"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Plus } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ApiError, apiFetch, json } from "@/lib/api/client";
import type { Course, Paginated } from "@/lib/api/types";
import { formatPrice, statusLabel } from "@/lib/format";


export default function InstructorCourses() {
  const { data, isLoading } = useQuery({
    queryKey: ["manage", "courses"],
    queryFn: () => apiFetch<Paginated<Course>>("/manage/courses"),
  });

  return (
    <>
      <PageHeader title="Kurslarım" description="Kurs oluştur, müfredatı düzenle ve yayınla." action={<NewCourseDialog />} />
      <div className="rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Kurs</TableHead>
              <TableHead>Durum</TableHead>
              <TableHead className="text-right">Ders</TableHead>
              <TableHead className="text-right">Fiyat</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading && (
              <TableRow>
                <TableCell colSpan={4} className="text-muted-foreground">
                  Yükleniyor…
                </TableCell>
              </TableRow>
            )}
            {data?.data.map((course) => (
              <TableRow key={course.id}>
                <TableCell>
                  <Link href={`/egitmen/kurslar/${course.id}`} className="font-medium hover:text-primary">
                    {course.title}
                  </Link>
                  <p className="text-xs text-muted-foreground">{course.instructor?.name}</p>
                </TableCell>
                <TableCell>
                  <Badge variant={course.status === "published" ? "default" : "secondary"}>{statusLabel[course.status]}</Badge>
                </TableCell>
                <TableCell className="text-right">{course.lessons_count}</TableCell>
                <TableCell className="text-right">{formatPrice(course.price)}</TableCell>
              </TableRow>
            ))}
            {data?.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-10 text-center text-muted-foreground">
                  Henüz kursun yok. İlk kursunu oluştur.
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
    </>
  );
}

function NewCourseDialog() {
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [price, setPrice] = useState("0");
  const [error, setError] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      apiFetch<{ data: Course }>("/manage/courses", {
        method: "POST",
        body: json({ title, price: Math.round(Number(price) * 100) }),
      }),
    onSuccess: ({ data }) => router.push(`/egitmen/kurslar/${data.id}`),
    onError: (e) => setError(e instanceof ApiError ? (Object.values(e.fieldErrors())[0] ?? e.message) : "Hata"),
  });

  return (
    <Dialog>
      <DialogTrigger render={<Button />}>
        <Plus /> Yeni kurs
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Yeni kurs</DialogTitle>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            create.mutate();
          }}
        >
          <div className="space-y-1.5">
            <Label htmlFor="title">Başlık</Label>
            <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} required minLength={3} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="price">Fiyat (TL)</Label>
            <Input id="price" type="number" min={0} step="1" value={price} onChange={(e) => setPrice(e.target.value)} />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            Oluştur ve düzenle
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}
