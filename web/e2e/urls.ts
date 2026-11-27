export const PORT = process.env.E2E_PORT ?? "3300";

export const brandUrl = (slug: string, path = "/") => `http://${slug}.localhost:${PORT}${path}`;
export const platformUrl = (path = "/") => `http://localhost:${PORT}${path}`;

import { expect, type Page } from "@playwright/test";

/** The header auth links render only after the client has hydrated and asked /auth/me. */
export async function waitForApp(page: Page) {
  await expect(page.getByRole("banner").getByRole("link", { name: /Giriş yap|Panelim/ }).first()).toBeVisible();
}
