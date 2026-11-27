import Link from "next/link";
import { Clock, PlayCircle } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { formatDuration, formatPrice, levelLabel } from "@/lib/format";
import type { Course } from "@/lib/api/types";

export function CourseCard({ course }: { course: Course }) {
  return (
    <Link
      href={`/kurslar/${course.slug}`}
      className="group flex flex-col overflow-hidden rounded-xl border bg-card transition hover:-translate-y-0.5 hover:shadow-lg"
    >
      <div className="brand-gradient relative aspect-[16/9]">
        {course.cover_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={course.cover_url} alt="" className="absolute inset-0 size-full object-cover" />
        ) : (
          <div className="absolute inset-0 flex items-end p-4">
            <span className="font-heading text-xl leading-tight font-bold text-white/95 drop-shadow">{course.title}</span>
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex flex-wrap gap-1.5">
          {course.category && <Badge variant="secondary">{course.category}</Badge>}
          <Badge variant="outline">{levelLabel[course.level]}</Badge>
        </div>
        <h3 className="font-heading leading-snug font-semibold group-hover:text-primary">{course.title}</h3>
        <p className="line-clamp-2 text-sm text-muted-foreground">{course.summary}</p>
        <div className="mt-auto flex items-center justify-between pt-2 text-sm">
          <span className="flex items-center gap-3 text-muted-foreground">
            <span className="flex items-center gap-1">
              <PlayCircle className="size-4" /> {course.lessons_count ?? 0} ders
            </span>
            {!!course.duration_seconds && (
              <span className="flex items-center gap-1">
                <Clock className="size-4" /> {formatDuration(course.duration_seconds)}
              </span>
            )}
          </span>
          <span className="font-semibold">{formatPrice(course.price)}</span>
        </div>
      </div>
    </Link>
  );
}
