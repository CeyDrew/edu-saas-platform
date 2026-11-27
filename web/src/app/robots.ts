import type { MetadataRoute } from "next";
import { headers } from "next/headers";

export default async function robots(): Promise<MetadataRoute.Robots> {
  const host = (await headers()).get("host");

  return {
    rules: { userAgent: "*", allow: "/", disallow: ["/panel", "/egitmen", "/yonetim", "/admin", "/odeme"] },
    sitemap: `http://${host}/sitemap.xml`,
  };
}
