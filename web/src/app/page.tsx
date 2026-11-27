import Link from "next/link";
import { ArrowUpRight, Building2, GraduationCap, ShieldCheck } from "lucide-react";
import { buttonVariants } from "@/components/ui/button";
import { api } from "@/lib/api/server";
import type { Tenant } from "@/lib/api/types";

const BASE_DOMAIN = process.env.TENANT_BASE_DOMAIN ?? "localhost";
const PORT = process.env.PORT ?? "3300";

export default async function PlatformHome() {
  const brands = await api<{ data: Tenant[] }>("/brands").catch(() => ({ data: [] as Tenant[] }));

  return (
    <main className="mx-auto w-full max-w-5xl flex-1 space-y-12 px-4 py-16">
      <header className="space-y-4">
        <p className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
          <GraduationCap className="size-5" /> Edu SaaS
        </p>
        <h1 className="max-w-3xl font-heading text-4xl font-bold md:text-5xl">
          Tek altyapı, birçok eğitim markası.
        </h1>
        <p className="max-w-2xl text-lg text-muted-foreground">
          Her marka kendi alt alan adı, teması, kurs kataloğu ve öğrencileriyle çalışır. Platform yöneticisi tüm markaları tek
          panelden yönetir.
        </p>
        <Link href="/admin" className={buttonVariants({ variant: "outline" })}>
          <ShieldCheck /> Platform paneli
        </Link>
      </header>

      <section className="space-y-4">
        <h2 className="flex items-center gap-2 font-heading text-xl font-semibold">
          <Building2 className="size-5" /> Markalar
        </h2>
        <div className="grid gap-4 sm:grid-cols-3">
          {brands.data.map((brand) => (
            <a
              key={brand.slug}
              href={`http://${brand.slug}.${BASE_DOMAIN}:${PORT}`}
              className="group overflow-hidden rounded-2xl border bg-card transition hover:shadow-lg"
            >
              <div
                className="h-24"
                style={{ background: `linear-gradient(135deg, ${brand.theme.primary}, ${brand.theme.accent})` }}
              />
              <div className="space-y-1 p-4">
                <p className="flex items-center justify-between font-semibold">
                  {brand.name} <ArrowUpRight className="size-4 opacity-0 transition group-hover:opacity-100" />
                </p>
                <p className="text-sm text-muted-foreground">{brand.tagline}</p>
                <p className="pt-2 font-mono text-xs text-muted-foreground">
                  {brand.slug}.{BASE_DOMAIN}
                </p>
              </div>
            </a>
          ))}
        </div>
      </section>
    </main>
  );
}
