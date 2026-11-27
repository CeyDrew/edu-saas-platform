import type { Metadata } from "next";
import Link from "next/link";
import { Search } from "lucide-react";
import { CourseCard } from "@/components/brand/course-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { api } from "@/lib/api/server";
import type { Course, Paginated } from "@/lib/api/types";
import { levelLabel } from "@/lib/format";
import { cn } from "@/lib/utils";

export const metadata: Metadata = { title: "Kurslar" };

type Category = { name: string; courses_count: number };

export default async function CoursesPage({ params, searchParams }: PageProps<"/[tenant]/kurslar">) {
  const { tenant } = await params;
  const query = await searchParams;
  const q = typeof query.q === "string" ? query.q : "";
  const category = typeof query.category === "string" ? query.category : "";
  const level = typeof query.level === "string" ? query.level : "";
  const page = typeof query.page === "string" ? query.page : "1";

  const search = new URLSearchParams({ per_page: "12", page });
  if (q) search.set("q", q);
  if (category) search.set("category", category);
  if (level) search.set("level", level);

  const [courses, categories] = await Promise.all([
    api<Paginated<Course>>(`/courses?${search}`, { tenant }),
    api<{ data: Category[] }>("/categories", { tenant }),
  ]);

  const link = (changes: Record<string, string>) => {
    const next = new URLSearchParams({ ...(q && { q }), ...(category && { category }), ...(level && { level }), ...changes });
    for (const [key, value] of [...next.entries()]) if (!value) next.delete(key);
    return `/kurslar${next.size ? `?${next}` : ""}`;
  };

  return (
    <div className="mx-auto max-w-6xl space-y-8 px-4 py-10">
      <div className="space-y-4">
        <h1 className="font-heading text-3xl font-bold">Kurslar</h1>
        <form className="flex max-w-xl gap-2" action="/kurslar">
          <div className="relative flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input name="q" defaultValue={q} placeholder="Kurs ara (yazım hatalarını tolere eder)" className="h-10 pl-9" />
          </div>
          {category && <input type="hidden" name="category" value={category} />}
          <Button type="submit" className="h-10 px-4">
            Ara
          </Button>
        </form>
      </div>

      <div className="flex flex-wrap gap-2 text-sm">
        <FilterChip href={link({ category: "" })} active={!category}>
          Tümü
        </FilterChip>
        {categories.data.map((item) => (
          <FilterChip key={item.name} href={link({ category: item.name })} active={category === item.name}>
            {item.name} <span className="opacity-60">{item.courses_count}</span>
          </FilterChip>
        ))}
        <span className="mx-2 border-l" />
        {Object.entries(levelLabel).map(([value, label]) => (
          <FilterChip key={value} href={link({ level: level === value ? "" : value })} active={level === value}>
            {label}
          </FilterChip>
        ))}
      </div>

      {courses.data.length === 0 ? (
        <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
          Aramana uygun kurs bulunamadı.
        </p>
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {courses.data.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
      )}

      {courses.meta.last_page > 1 && (
        <div className="flex justify-center gap-2">
          {Array.from({ length: courses.meta.last_page }, (_, i) => String(i + 1)).map((n) => (
            <FilterChip key={n} href={link({ page: n })} active={n === page}>
              {n}
            </FilterChip>
          ))}
        </div>
      )}
    </div>
  );
}

function FilterChip({ href, active, children }: { href: string; active: boolean; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className={cn(
        "rounded-full border px-3 py-1 transition",
        active ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted",
      )}
    >
      {children}
    </Link>
  );
}
