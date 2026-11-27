"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useParams } from "next/navigation";
import { ArrowLeft, Download } from "lucide-react";
import { RankBars } from "@/components/charts/charts";
import { PageHeader, StatCard } from "@/components/panel/panel-shell";
import { buttonVariants } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { apiFetch } from "@/lib/api/client";
import { formatDate } from "@/lib/format";

type Report = {
  summary: { students: number; average_progress: number; completion_rate: number; accuracy: number; answers: number };
  students: { name: string; email: string; completed_lessons: number; progress: number; accuracy: number | null; last_activity: string | null }[];
  lessons: { lesson: string; completed: number; accuracy: number | null }[];
  hardest_questions: { stem: string; lesson: string; answers: number; accuracy: number }[];
};

export default function CourseReport() {
  const { id } = useParams<{ id: string }>();
  const { data } = useQuery({
    queryKey: ["manage", "report", id],
    queryFn: async () => (await apiFetch<{ data: Report }>(`/manage/courses/${id}/report`)).data,
  });

  const s = data?.summary;

  return (
    <>
      <Link href={`/egitmen/kurslar/${id}`} className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Kursa dön
      </Link>
      <PageHeader
        title="Öğrenci performansı"
        action={
          <a href={`/api/manage/courses/${id}/report?format=csv`} className={buttonVariants({ variant: "outline" })}>
            <Download /> CSV
          </a>
        }
      />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Öğrenci" value={s?.students ?? "…"} />
        <StatCard label="Ortalama ilerleme" value={s ? `%${s.average_progress}` : "…"} />
        <StatCard label="Bitirme oranı" value={s ? `%${s.completion_rate}` : "…"} />
        <StatCard label="Doğru cevap oranı" value={s ? `%${s.accuracy}` : "…"} hint={s ? `${s.answers} cevap` : undefined} />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <section className="rounded-xl border bg-card p-5">
          <h2 className="mb-4 font-semibold">Dersi tamamlayan öğrenci</h2>
          {data && <RankBars data={data.lessons.map((l) => ({ name: l.lesson, value: l.completed }))} format={(v) => `${v} kişi`} />}
        </section>
        <section className="rounded-xl border bg-card p-5">
          <h2 className="mb-4 font-semibold">En çok yanlış yapılan sorular</h2>
          <ul className="space-y-3 text-sm">
            {data?.hardest_questions.map((q) => (
              <li key={`${q.lesson}-${q.stem}`} className="space-y-1">
                <p>{q.stem}</p>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <Progress value={q.accuracy} className="h-1.5 flex-1" />
                  <span>
                    %{q.accuracy} doğru · {q.answers} cevap · {q.lesson}
                  </span>
                </div>
              </li>
            ))}
            {data?.hardest_questions.length === 0 && <li className="text-muted-foreground">Yeterli cevap yok.</li>}
          </ul>
        </section>
      </div>

      <section className="mt-6 rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Öğrenci</TableHead>
              <TableHead className="w-48">İlerleme</TableHead>
              <TableHead className="text-right">Doğru oranı</TableHead>
              <TableHead className="text-right">Son etkinlik</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data?.students.map((student) => (
              <TableRow key={student.email}>
                <TableCell>
                  <p className="font-medium">{student.name}</p>
                  <p className="text-xs text-muted-foreground">{student.email}</p>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Progress value={student.progress} className="h-1.5" />
                    <span className="w-10 text-right text-xs">%{student.progress}</span>
                  </div>
                </TableCell>
                <TableCell className="text-right">{student.accuracy === null ? "-" : `%${student.accuracy}`}</TableCell>
                <TableCell className="text-right text-xs">{formatDate(student.last_activity)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </section>
    </>
  );
}
