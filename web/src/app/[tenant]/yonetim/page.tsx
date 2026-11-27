"use client";

import { useQuery } from "@tanstack/react-query";
import { PageHeader, StatCard } from "@/components/panel/panel-shell";
import { apiFetch } from "@/lib/api/client";
import type { Course, Paginated, User } from "@/lib/api/types";

export default function BrandOverview() {
  const students = useQuery({
    queryKey: ["brand", "members", "student", ""],
    queryFn: () => apiFetch<Paginated<User>>("/brand/members?role=student"),
  });
  const courses = useQuery({
    queryKey: ["manage", "courses"],
    queryFn: () => apiFetch<Paginated<Course>>("/manage/courses"),
  });

  return (
    <>
      <PageHeader title="Genel bakış" description="Markanın öğrenci ve kurs durumu." />
      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard label="Öğrenci" value={students.data?.meta.total ?? "…"} />
        <StatCard label="Kurs" value={courses.data?.meta.total ?? "…"} />
        <StatCard
          label="Yayında"
          value={courses.data ? courses.data.data.filter((course) => course.status === "published").length : "…"}
        />
      </div>
    </>
  );
}
