import { expect, test } from "@playwright/test";
import { brandUrl, platformUrl } from "./urls";

const brands = [
  { slug: "kodakademi", name: "Kod Akademi", primary: "rgb(79, 70, 229)", course: "Sıfırdan PHP ve Laravel" },
  { slug: "dilokulu", name: "Lingua Dil Okulu", primary: "rgb(225, 29, 72)", course: "İngilizce A1: Sıfırdan Konuşma" },
  { slug: "zirvedershane", name: "Zirve Dershanesi", primary: "rgb(5, 150, 105)", course: "TYT Matematik Kampı" },
];

for (const brand of brands) {
  test(`${brand.name} opens with its own theme and catalog`, async ({ page }) => {
    await page.goto(brandUrl(brand.slug));

    await expect(page).toHaveTitle(new RegExp(brand.name));
    await expect(page.getByRole("link", { name: brand.name }).first()).toBeVisible();

    const primary = await page.locator(".brand").evaluate((el) => getComputedStyle(el).getPropertyValue("--brand-primary").trim());
    const swatch = await page.evaluate((color) => {
      const probe = document.createElement("span");
      probe.style.color = color;
      document.body.append(probe);
      return getComputedStyle(probe).color;
    }, primary);
    expect(swatch).toBe(brand.primary);

    await page.goto(brandUrl(brand.slug, "/kurslar"));
    await expect(page.getByRole("heading", { name: brand.course })).toBeVisible();

    // Other brands courses never leak into this catalog.
    for (const other of brands.filter((b) => b.slug !== brand.slug)) {
      await expect(page.getByRole("heading", { name: other.course })).toHaveCount(0);
    }
  });
}

test("course pages are server rendered with SEO metadata", async ({ page }) => {
  // Without JavaScript, everything below must come from the server render.
  await page.context().route("**/*.js", (route) => route.abort());
  await page.goto(brandUrl("zirvedershane", "/kurslar/tyt-matematik-kampi"));

  await expect(page).toHaveTitle("TYT Matematik Kampı | Zirve Dershanesi");
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute("content", "TYT Matematik Kampı");
  await expect(page.getByRole("heading", { name: "Müfredat" })).toBeVisible();

  const jsonLd = JSON.parse((await page.locator('script[type="application/ld+json"]').textContent()) ?? "{}");
  expect(jsonLd["@type"]).toBe("Course");
  expect(jsonLd.offers.priceCurrency).toBe("TRY");
});

test("the platform home lists every brand", async ({ page }) => {
  await page.goto(platformUrl());
  for (const brand of brands) await expect(page.getByText(brand.name)).toBeVisible();
});
