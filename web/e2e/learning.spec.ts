import { expect, test } from "@playwright/test";
import { brandUrl } from "./urls";

const BRAND = "kodakademi";

test("a student answers a video task, takes the quiz and finds the mistake in the archive", async ({ page }) => {
  await page.goto(brandUrl(BRAND, "/giris"));
  await page.getByLabel("E-posta").fill(`ogrenci@${BRAND}.test`);
  await page.getByLabel("Şifre").fill("password");
  await page.getByRole("button", { name: "Giriş yap" }).click();
  await expect(page).toHaveURL(/\/panel$/);

  await page.getByTestId("my-courses").getByRole("link").first().click();
  await expect(page).toHaveURL(/\/ogren\//);
  await expect(page.getByTestId("course-progress")).toBeVisible();

  // Jump to the first lesson and seek the demo player past the video task.
  await page.getByRole("button", { name: /Değişkenler|Routing|Commit|İmaj|JSX/ }).first().click();
  const timeline = page.getByLabel("Zaman çizelgesi");
  const max = Number(await timeline.getAttribute("max"));
  await timeline.fill(String(Math.round(max / 2)));

  const task = page.getByTestId("video-task");
  await expect(task).toBeVisible();
  // Pick an option; whatever the result, the explanation and "continue" appear.
  await task.getByRole("button").first().click();
  await task.getByRole("button", { name: "Cevapla" }).click();
  await expect(task.getByText(/Doğru!|Yanlış\./)).toBeVisible();
  await task.getByRole("button", { name: "Videoya devam et" }).click();
  await expect(task).toBeHidden();

  // End-of-lesson quiz: answer the second option everywhere so at least one is wrong.
  const quiz = page.getByTestId("quiz");
  for (const fieldset of await quiz.locator("fieldset").all()) {
    await fieldset.getByRole("radio").nth(3).check();
  }
  await quiz.getByRole("button", { name: "Değerlendirmeyi bitir" }).click();
  await expect(page.getByTestId("quiz-score")).toContainText("/2");

  await page.goto(brandUrl(BRAND, "/panel/tekrar"));
  await page.getByRole("tab", { name: "Yanlış arşivi" }).click();
  await expect(page.getByText(/soru arşivde/)).toBeVisible();
  await expect(page.getByText("Doğru cevap:").first()).toBeVisible();
});
