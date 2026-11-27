import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { SiteHeader } from "@/components/brand/site-header";
import { brandStyle, getTenant } from "@/lib/tenant";

export async function generateMetadata({ params }: LayoutProps<"/[tenant]">): Promise<Metadata> {
  const { tenant: slug } = await params;
  const tenant = await getTenant(slug);
  if (!tenant) return {};

  return {
    title: { default: `${tenant.name} | Online eğitim`, template: `%s | ${tenant.name}`, absolute: `${tenant.name} | Online eğitim` },
    description: tenant.tagline ?? undefined,
    openGraph: { siteName: tenant.name, locale: "tr_TR", type: "website" },
  };
}

export default async function TenantLayout({ children, params }: LayoutProps<"/[tenant]">) {
  const { tenant: slug } = await params;
  const tenant = await getTenant(slug);
  if (!tenant) notFound();

  return (
    <div className="brand flex min-h-screen flex-col font-sans" style={brandStyle(tenant)}>
      <SiteHeader tenant={tenant} />
      <main className="flex-1">{children}</main>
      <footer className="border-t py-8 text-center text-sm text-muted-foreground">
        © {new Date().getFullYear()} {tenant.name} · {tenant.tagline}
      </footer>
    </div>
  );
}
