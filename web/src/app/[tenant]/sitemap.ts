import type { MetadataRoute } from "next";
import { headers } from "next/headers";
import { api } from "@/lib/api/server";
import { tenantFromHost } from "@/lib/host";
import type { Course, Paginated } from "@/lib/api/types";

/** Per-brand sitemap at <brand>.localhost/sitemap.xml (the proxy rewrites it here). */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const host = (await headers()).get("host");
  const tenant = tenantFromHost(host);
  if (!tenant) return [];
  const base = `http://${host}`;
  const courses = await api<Paginated<Course>>("/courses?per_page=48", { tenant });

  return [
    { url: `${base}/`, changeFrequency: "daily", priority: 1 },
    { url: `${base}/kurslar`, changeFrequency: "daily", priority: 0.9 },
    ...courses.data.map((course) => ({
      url: `${base}/kurslar/${course.slug}`,
      lastModified: course.published_at ?? undefined,
      changeFrequency: "weekly" as const,
      priority: 0.8,
    })),
  ];
}
