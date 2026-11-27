import Link from "next/link";
import { ArrowRight, Brain, Repeat, Video } from "lucide-react";
import { notFound } from "next/navigation";
import { CourseCard } from "@/components/brand/course-card";
import { buttonVariants } from "@/components/ui/button";
import { api } from "@/lib/api/server";
import type { Course, Paginated, Tenant } from "@/lib/api/types";
import { getTenant } from "@/lib/tenant";
import { cn } from "@/lib/utils";

const features = [
  { icon: Video, title: "Video görevleri", text: "Videonun tam o dakikasında çıkan sorularla aktif izleme." },
  { icon: Repeat, title: "Kişisel tekrar", text: "Yanlış yaptığın sorular aralıklı tekrar planına girer." },
  { icon: Brain, title: "Yapay zekâ destekli", text: "Ders içeriğinden üretilen, eğitmen onaylı test soruları." },
];

function Hero({ tenant, courseCount }: { tenant: Tenant; courseCount: number }) {
  const variant = tenant.theme.hero ?? "gradient";
  const onGradient = variant === "gradient";
  const actions = (
    <div className="flex flex-wrap gap-3">
      <Link
        href="/kurslar"
        className={cn(buttonVariants({ size: "lg" }), "h-11 px-5", onGradient && "bg-white text-black hover:bg-white/90")}
      >
        Kursları keşfet <ArrowRight />
      </Link>
      <Link
        href="/kayit"
        className={cn(
          buttonVariants({ variant: "outline", size: "lg" }),
          "h-11 px-5",
          onGradient && "border-white/50 bg-transparent text-white hover:bg-white/10 hover:text-white",
        )}
      >
        Ücretsiz hesap aç
      </Link>
    </div>
  );

  if (variant === "split") {
    return (
      <section className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:grid-cols-2">
        <div className="space-y-6">
          <h1 className="font-heading text-4xl leading-tight font-bold md:text-5xl">{tenant.name}</h1>
          <p className="text-lg text-muted-foreground">{tenant.tagline}</p>
          {actions}
        </div>
        <div className="brand-gradient aspect-[4/3] rounded-3xl p-8 text-white shadow-xl">
          <p className="font-heading text-3xl font-semibold">Hello! Hallo! ¡Hola!</p>
          <p className="mt-3 text-white/80">Canlı pratik, kısa videolar ve kişisel tekrar planı.</p>
        </div>
      </section>
    );
  }

  if (variant === "stats") {
    return (
      <section className="border-b bg-muted/40">
        <div className="mx-auto max-w-6xl space-y-8 px-4 py-16">
          <h1 className="max-w-3xl font-heading text-4xl font-extrabold md:text-5xl">
            <span className="text-primary">{tenant.name}</span> ile hedefine bir adım daha yaklaş
          </h1>
          <p className="max-w-2xl text-lg text-muted-foreground">{tenant.tagline}</p>
          {actions}
          <dl className="grid max-w-2xl grid-cols-3 gap-4">
            {[
              [String(courseCount), "kurs"],
              ["7/24", "erişim"],
              ["SM-2", "tekrar planı"],
            ].map(([value, label]) => (
              <div key={label} className="rounded-xl border bg-background p-4">
                <dt className="text-sm text-muted-foreground">{label}</dt>
                <dd className="font-heading text-2xl font-bold text-primary">{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>
    );
  }

  return (
    <section className="brand-gradient text-white">
      <div className="mx-auto max-w-6xl space-y-6 px-4 py-20">
        <h1 className="max-w-3xl font-heading text-4xl leading-tight font-bold md:text-6xl">{tenant.tagline}</h1>
        <p className="max-w-xl text-lg text-white/80">{tenant.name} ile projeler üzerinden öğren, ilerlemeni takip et.</p>
        {actions}
      </div>
    </section>
  );
}

export default async function BrandHome({ params }: PageProps<"/[tenant]">) {
  const { tenant: slug } = await params;
  const tenant = await getTenant(slug);
  if (!tenant) notFound();

  const courses = await api<Paginated<Course>>("/courses?per_page=6", { tenant: slug });

  return (
    <>
      <Hero tenant={tenant} courseCount={courses.meta.total} />
      <section className="mx-auto max-w-6xl px-4 py-14">
        <div className="mb-6 flex items-end justify-between">
          <h2 className="font-heading text-2xl font-bold">Öne çıkan kurslar</h2>
          <Link href="/kurslar" className="text-sm text-primary hover:underline">
            Tümünü gör
          </Link>
        </div>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {courses.data.map((course) => (
            <CourseCard key={course.id} course={course} />
          ))}
        </div>
      </section>
      <section className="border-t bg-muted/30">
        <div className="mx-auto grid max-w-6xl gap-6 px-4 py-14 md:grid-cols-3">
          {features.map(({ icon: Icon, title, text }) => (
            <div key={title} className="space-y-2">
              <Icon className="size-8 text-primary" />
              <h3 className="font-heading font-semibold">{title}</h3>
              <p className="text-sm text-muted-foreground">{text}</p>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
