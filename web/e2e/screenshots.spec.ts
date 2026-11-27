import { expect, test, type Page } from "@playwright/test";
import path from "node:path";
import { brandUrl, platformUrl } from "./urls";

/**
 * Regenerates docs/screenshots. Skipped in normal runs:
 *   SCREENSHOTS=1 npx playwright test screenshots
 */
test.skip(!process.env.SCREENSHOTS, "Set SCREENSHOTS=1 to refresh the README screenshots.");
test.use({ viewport: { width: 1360, height: 850 } });

const out = (name: string) => path.join(__dirname, "..", "..", "docs", "screenshots", `${name}.png`);

async function login(page: Page, url: string, email: string) {
  await page.goto(url);
  await page.getByLabel("E-posta").fill(email);
  await page.getByLabel("Şifre").fill("password");
  await page.getByRole("button", { name: "Giriş yap" }).click();
  await page.waitForURL((u) => !u.pathname.includes("giris"));
}

async function shot(page: Page, name: string, url?: string) {
  if (url) await page.goto(url);
  await page.waitForLoadState("networkidle");
  await page.screenshot({ path: out(name) });
}

test("storefronts", async ({ page }) => {
  await shot(page, "01-platform", platformUrl());
  await shot(page, "02-brand-kodakademi", brandUrl("kodakademi"));
  await shot(page, "03-brand-dilokulu", brandUrl("dilokulu"));
  await shot(page, "04-brand-zirvedershane", brandUrl("zirvedershane"));
  await shot(page, "05-course-page", brandUrl("zirvedershane", "/kurslar/tyt-matematik-kampi"));
});

test("student", async ({ page }) => {
  await login(page, brandUrl("kodakademi", "/giris"), "ogrenci@kodakademi.test");
  await shot(page, "06-student-courses", brandUrl("kodakademi", "/panel"));

  await page.goto(brandUrl("kodakademi", "/ogren/sifirdan-php-ve-laravel"));
  const timeline = page.getByLabel("Zaman çizelgesi");
  await timeline.fill(String(Math.round(Number(await timeline.getAttribute("max")) / 2)));
  await expect(page.getByTestId("video-task")).toBeVisible();
  await shot(page, "07-video-task");

  await shot(page, "08-review", brandUrl("kodakademi", "/panel/tekrar"));
  await shot(page, "09-checkout", brandUrl("kodakademi", "/odeme?kurs=docker-ile-gelistirme-ortami"));
});

test("brand admin and instructor", async ({ page }) => {
  await login(page, brandUrl("kodakademi", "/giris"), "yonetici@kodakademi.test");
  await shot(page, "10-sales-analytics", brandUrl("kodakademi", "/yonetim"));
  await shot(page, "11-ai-review", brandUrl("kodakademi", "/egitmen/ai-sorular"));

  await page.goto(brandUrl("kodakademi", "/egitmen"));
  await page.getByRole("link", { name: "Sıfırdan PHP ve Laravel" }).click();
  await page.waitForURL(/kurslar\/\d+$/);
  await shot(page, "12-course-editor");
  await shot(page, "13-course-report", `${page.url()}/rapor`);
});

test("platform admin", async ({ page }) => {
  await login(page, platformUrl("/admin/giris"), "admin@edusaas.test");
  await shot(page, "14-platform-brands", platformUrl("/admin"));
  await shot(page, "15-platform-reports", platformUrl("/admin/raporlar"));
});
