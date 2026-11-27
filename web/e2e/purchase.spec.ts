import { expect, test, type Page } from "@playwright/test";
import { brandUrl } from "./urls";

const BRAND = "dilokulu";

/** A fresh student per run keeps the test repeatable against the seeded database. */
async function signUp(page: Page) {
  const email = `e2e-${Date.now()}@example.com`;
  await page.goto(brandUrl(BRAND, "/kayit"));
  await page.getByLabel("Ad soyad").fill("Deneme Öğrenci");
  await page.getByLabel("E-posta").fill(email);
  await page.getByLabel("Şifre", { exact: true }).fill("gizli-sifre-123");
  await page.getByLabel("Şifre tekrar").fill("gizli-sifre-123");
  await page.getByRole("button", { name: "Hesap oluştur" }).click();
  await expect(page).toHaveURL(/\/panel$/);
  return email;
}

async function fillIndividualBilling(page: Page) {
  await page.getByLabel("Ad soyad").fill("Deneme Öğrenci");
  await page.getByLabel("TC kimlik no").fill("10000000146");
  await page.getByLabel("Adres").fill("Moda Caddesi 1");
}

test("a student buys a course with a coupon and starts learning", async ({ page }) => {
  await signUp(page);

  await page.goto(brandUrl(BRAND, "/kurslar/ingilizce-a1-sifirdan-konusma"));
  await page.getByRole("link", { name: "Satın al" }).click();
  await expect(page).toHaveURL(/\/odeme\?kurs=ingilizce-a1-sifirdan-konusma/);

  // Coupon from the seeder: 20% off 349 TL.
  await page.getByPlaceholder("Kupon kodu").fill("HOSGELDIN20");
  await page.getByRole("button", { name: "Uygula" }).click();
  await expect(page.getByText("İndirim (HOSGELDIN20)")).toBeVisible();

  // Invalid TCKN is rejected by the checksum rule.
  await fillIndividualBilling(page);
  await page.getByLabel("TC kimlik no").fill("12345678901");
  await page.getByRole("button", { name: /Ödemeye geç/ }).click();
  await expect(page.getByText(/geçerli bir T\.?C\.? kimlik/i)).toBeVisible();

  await page.getByLabel("TC kimlik no").fill("10000000146");
  await page.getByRole("button", { name: /Ödemeye geç · ₺279/ }).click();

  // Hosted payment page (fake provider in local mode).
  await expect(page).toHaveURL(/\/odeme\/sahte\?token=/);
  await page.getByRole("button", { name: "Ödemeyi onayla" }).click();

  await expect(page.getByTestId("order-result")).toHaveAttribute("data-status", "paid");
  await expect(page.getByRole("heading", { name: "Ödeme başarılı" })).toBeVisible();

  await page.getByRole("link", { name: "Kurslarıma git" }).click();
  await expect(page.getByTestId("my-courses").getByText("İngilizce A1: Sıfırdan Konuşma")).toBeVisible();

  await page.goto(brandUrl(BRAND, "/panel/siparisler"));
  await expect(page.getByRole("cell", { name: "Ödendi" })).toBeVisible();
});

test("a declined card leaves the order failed and the course locked", async ({ page }) => {
  await signUp(page);

  await page.goto(brandUrl(BRAND, "/odeme?kurs=almanca-a1-baslangic"));
  await fillIndividualBilling(page);
  await page.getByRole("button", { name: /Ödemeye geç/ }).click();
  await page.getByRole("button", { name: "Reddedilen kartı dene" }).click();

  await expect(page.getByTestId("order-result")).toHaveAttribute("data-status", "failed");

  await page.goto(brandUrl(BRAND, "/panel"));
  await expect(page.getByText("Henüz bir kursun yok.")).toBeVisible();
});
