import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end tests run against the full docker compose stack (seeded database).
 * Brands are reached through their subdomains, e.g. http://kodakademi.localhost:3300.
 */
export default defineConfig({
  testDir: "./e2e",
  timeout: 120_000,
  // Generous: the first hit of a page in `next dev` compiles it.
  expect: { timeout: 30_000 },
  fullyParallel: false,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? "github" : "list",
  use: {
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    locale: "tr-TR",
    // Docker Desktop forwards IPv4 reliably; keep brand subdomains on 127.0.0.1.
    launchOptions: { args: ["--host-resolver-rules=MAP *.localhost 127.0.0.1, MAP localhost 127.0.0.1"] },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
