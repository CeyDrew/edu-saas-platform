"use client";

import type { ApiErrorBody } from "./types";

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: ApiErrorBody,
  ) {
    super(body.message ?? `API error ${status}`);
  }

  /** First validation message per field, for forms. */
  fieldErrors(): Record<string, string> {
    return Object.fromEntries(Object.entries(this.body.errors ?? {}).map(([key, value]) => [key, value[0]]));
  }
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

let csrfReady: Promise<void> | null = null;

/** Sanctum SPA auth: fetch the CSRF cookie once before the first state-changing call. */
async function ensureCsrf() {
  if (xsrfToken()) return;
  csrfReady ??= fetch("/sanctum/csrf-cookie", { credentials: "include" }).then(() => undefined);
  await csrfReady;
}

/**
 * Browser calls go to the same origin; the Next.js proxy forwards them to Laravel with
 * the current brand, so cookies stay first-party per brand subdomain.
 */
export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (method !== "GET" && method !== "HEAD") {
    await ensureCsrf();
    const token = xsrfToken();
    if (token) headers.set("X-XSRF-TOKEN", token);
    if (init.body && !(init.body instanceof FormData)) headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`/api${path}`, { ...init, method, headers, credentials: "include" });

  if (response.status === 204) return undefined as T;

  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(response.status, body as ApiErrorBody);

  return body as T;
}

export const json = (data: unknown) => JSON.stringify(data);
