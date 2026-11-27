import "server-only";
import { cache } from "react";
import { apiOrNull } from "@/lib/api/server";
import type { Tenant } from "@/lib/api/types";

/** Deduplicated per request, shared by layouts, pages and generateMetadata. */
export const getTenant = cache(async (slug: string): Promise<Tenant | null> => {
  const response = await apiOrNull<{ data: Tenant }>("/tenant", { tenant: slug });
  return response?.data ?? null;
});

export const fontVariable = {
  sans: "var(--font-inter)",
  serif: "var(--font-lora)",
  rounded: "var(--font-nunito)",
} as const;

export function brandStyle(tenant: Tenant): React.CSSProperties {
  return {
    "--brand-primary": tenant.theme.primary ?? "#111827",
    "--brand-accent": tenant.theme.accent ?? "#6b7280",
    "--brand-font": fontVariable[tenant.theme.font ?? "sans"],
  } as React.CSSProperties;
}
