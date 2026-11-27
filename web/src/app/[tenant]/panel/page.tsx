"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { PlayCircle } from "lucide-react";
import { PageHeader } from "@/components/panel/panel-shell";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { apiFetch } from "@/lib/api/client";
import type { MyCourse } from "@/lib/api/types";

const sourceLabel = { purchase: "Satın alındı", free: "Ücretsiz", subscription: "Abonelik" } as const;

export default function MyCourses() {
  const { data, isLoading } = useQuery({
    queryKey: ["me", "courses"],
    queryFn: async () => (await apiFetch<{ data: MyCourse[] }>("/me/courses")).data,
  });

  return (
    <>
      <PageHeader title="Kurslarım" description="Kaldığın yerden devam et." />
      {isLoading && <p className="text-muted-foreground">Yükleniyor…</p>}
      {data?.length === 0 && (
        <div className="rounded-xl border border-dashed p-10 text-center">
          <p className="mb-4 text-muted-foreground">Henüz bir kursun yok.</p>
          <Link href="/kurslar" className={buttonVariants()}>
            Kurslara göz at
          </Link>
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-2" data-testid="my-courses">
        {data?.map(({ course, source, progress }) => (
          <Link
            key={course.id}
            href={`/ogren/${course.slug}`}
            className="group space-y-3 rounded-xl border bg-card p-4 transition hover:shadow-md"
          >
            <div className="flex items-start justify-between gap-2">
              <h3 className="font-heading font-semibold group-hover:text-primary">{course.title}</h3>
              <Badge variant="secondary">{sourceLabel[source]}</Badge>
            </div>
            <Progress value={progress?.percent ?? 0} />
            <p className="flex items-center justify-between text-sm text-muted-foreground">
              <span>
                {progress?.completed_lessons ?? 0}/{course.lessons_count} ders · %{progress?.percent ?? 0}
              </span>
              <span className="flex items-center gap-1 text-primary">
                <PlayCircle className="size-4" /> Devam et
              </span>
            </p>
          </Link>
        ))}
      </div>
    </>
  );
}
