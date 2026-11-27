import { NextResponse, type NextRequest } from "next/server";
import { tenantFromHost } from "@/lib/host";

const API_URL = process.env.API_INTERNAL_URL ?? "http://localhost:8000";

/**
 * One Next.js app serves every brand:
 * - `/api/*` and `/sanctum/*` are forwarded to Laravel on the same origin, so session
 *   cookies stay first-party. The brand is sent as X-Tenant (client values are dropped).
 * - Brand pages are rewritten to `/<tenant>/...`; the platform host keeps its own routes.
 */
export function proxy(request: NextRequest) {
  const tenant = tenantFromHost(request.headers.get("host"));
  const { pathname, search } = request.nextUrl;

  if (pathname.startsWith("/api/") || pathname.startsWith("/sanctum/")) {
    const headers = new Headers(request.headers);
    headers.delete("x-tenant");
    if (tenant) headers.set("x-tenant", tenant);
    headers.set("accept", "application/json");

    return NextResponse.rewrite(new URL(`${pathname}${search}`, API_URL), { request: { headers } });
  }

  if (!tenant) return NextResponse.next();

  const url = request.nextUrl.clone();
  url.pathname = `/${tenant}${pathname === "/" ? "" : pathname}`;

  return NextResponse.rewrite(url);
}

export const config = {
  matcher: ["/((?!_next/|favicon.ico|robots.txt|.*\.(?:png|jpg|jpeg|svg|webp|ico)$).*)"],
};
