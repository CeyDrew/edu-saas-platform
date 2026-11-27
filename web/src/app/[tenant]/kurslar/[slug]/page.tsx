import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CheckCircle2, Clock, Eye, Lock, PlayCircle, UserRound } from "lucide-react";
import { BuyBox } from "@/components/brand/buy-box";
import { Badge } from "@/components/ui/badge";
import { apiOrNull } from "@/lib/api/server";
import type { Course } from "@/lib/api/types";
import { formatDuration, formatPrice, levelLabel } from "@/lib/format";
import { getTenant } from "@/lib/tenant";

async function getCourse(tenant: string, slug: string) {
  return (await apiOrNull<{ data: Course }>(`/courses/${encodeURIComponent(slug)}`, { tenant }))?.data ?? null;
}

export async function generateMetadata({ params }: PageProps<"/[tenant]/kurslar/[slug]">): Promise<Metadata> {
  const { tenant, slug } = await params;
  const course = await getCourse(tenant, slug);
  if (!course) return {};

  return {
    title: course.title,
    description: course.summary ?? undefined,
    alternates: { canonical: `/kurslar/${course.slug}` },
    openGraph: {
      title: course.title,
      description: course.summary ?? undefined,
      type: "website",
      images: course.cover_url ? [course.cover_url] : undefined,
    },
  };
}

export default async function CoursePage({ params }: PageProps<"/[tenant]/kurslar/[slug]">) {
  const { tenant: slug, slug: courseSlug } = await params;
  const [tenant, course] = await Promise.all([getTenant(slug), getCourse(slug, courseSlug)]);
  if (!tenant || !course) notFound();

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Course",
    name: course.title,
    description: course.summary,
    provider: { "@type": "Organization", name: tenant.name },
    offers: {
      "@type": "Offer",
      price: (course.price / 100).toFixed(2),
      priceCurrency: "TRY",
      category: course.price ? "Paid" : "Free",
    },
  };

  return (
    <div>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <section className="brand-gradient text-white">
        <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 lg:grid-cols-[1fr_340px]">
          <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
              {course.category && <Badge className="bg-white/20 text-white">{course.category}</Badge>}
              <Badge className="bg-white/20 text-white">{levelLabel[course.level]}</Badge>
            </div>
            <h1 className="font-heading text-3xl font-bold md:text-4xl">{course.title}</h1>
            <p className="text-lg text-white/85">{course.summary}</p>
            <div className="flex flex-wrap gap-5 text-sm text-white/80">
              <span className="flex items-center gap-1.5">
                <UserRound className="size-4" /> {course.instructor?.name}
              </span>
              <span className="flex items-center gap-1.5">
                <PlayCircle className="size-4" /> {course.lessons_count} ders
              </span>
              <span className="flex items-center gap-1.5">
                <Clock className="size-4" /> {formatDuration(course.duration_seconds ?? 0)}
              </span>
            </div>
          </div>
        </div>
      </section>

      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 lg:grid-cols-[1fr_340px]">
        <div className="space-y-8">
          <section className="space-y-3">
            <h2 className="font-heading text-xl font-bold">Kurs hakkında</h2>
            <p className="whitespace-pre-line text-muted-foreground">{course.description}</p>
          </section>

          <section className="space-y-3">
            <h2 className="font-heading text-xl font-bold">Müfredat</h2>
            <div className="divide-y rounded-xl border">
              {course.sections?.map((section) => (
                <div key={section.id} className="p-4">
                  <h3 className="mb-2 font-semibold">{section.title}</h3>
                  <ul className="space-y-1.5 text-sm">
                    {section.lessons?.map((lesson) => (
                      <li key={lesson.id} className="flex items-center gap-2">
                        {lesson.is_preview ? (
                          <Eye className="size-4 text-primary" />
                        ) : (
                          <Lock className="size-4 text-muted-foreground" />
                        )}
                        <span className="flex-1">{lesson.title}</span>
                        {lesson.is_preview && <Badge variant="outline">Önizleme</Badge>}
                        <span className="text-muted-foreground">{formatDuration(lesson.duration_seconds)}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          </section>
        </div>

        <aside className="lg:-mt-48">
          <div className="sticky top-24 space-y-4 rounded-2xl border bg-card p-6 shadow-lg">
            <div className="flex items-baseline gap-2">
              <span className="font-heading text-3xl font-bold">{formatPrice(course.price)}</span>
              {course.compare_at_price && (
                <span className="text-muted-foreground line-through">{formatPrice(course.compare_at_price)}</span>
              )}
            </div>
            <BuyBox course={course} />
            <ul className="space-y-2 text-sm text-muted-foreground">
              {["Ömür boyu erişim", "Video görevleri ve testler", "Kişisel tekrar planı"].map((item) => (
                <li key={item} className="flex items-center gap-2">
                  <CheckCircle2 className="size-4 text-primary" /> {item}
                </li>
              ))}
              {course.included_in_subscription && (
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="size-4 text-primary" /> Abonelik ile de erişilebilir
                </li>
              )}
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
