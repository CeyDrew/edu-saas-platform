import "server-only";
import { cookies, headers as requestHeaders } from "next/headers";
import type { ApiErrorBody } from "./types";

const API_URL = process.env.API_INTERNAL_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: ApiErrorBody,
  ) {
    super(body.message ?? `API error ${status}`);
  }
}

type Options = {
  tenant?: string | null;
  /** Forward the visitor's session cookie (for SSR of signed-in pages). */
  auth?: boolean;
  init?: RequestInit;
};

/**
 * Fetch the Laravel API from Server Components. Calls go straight to the API
 * container with the brand in X-Tenant.
 */
export async function api<T>(path: string, { tenant, auth = false, init }: Options = {}): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set("Accept", "application/json");
  if (tenant) headers.set("X-Tenant", tenant);

  if (auth) {
    const jar = await cookies();
    headers.set("Cookie", jar.toString());
    // Sanctum treats the request as stateful (session cookie) only with a known referer.
    const host = (await requestHeaders()).get("host") ?? "localhost:3300";
    headers.set("Referer", `http://${host}/`);
  }

  const response = await fetch(`${API_URL}/api${path}`, { ...init, headers, cache: "no-store" });

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as ApiErrorBody;
    throw new ApiError(response.status, body);
  }

  return (await response.json()) as T;
}

/** Like api(), but returns null on 404 so pages can call notFound(). */
export async function apiOrNull<T>(path: string, options: Options = {}): Promise<T | null> {
  try {
    return await api<T>(path, options);
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 401)) return null;
    throw error;
  }
}
