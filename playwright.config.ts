import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E configuration
 *
 * ── Running modes ──────────────────────────────────────────────────────────
 *
 *  1. Headless inside Docker (CI / docker compose --profile e2e run --rm e2e):
 *       docker compose --profile e2e run --rm e2e
 *       # CI runs four critical specs via docker/ci-e2e-runner.sh
 *       docker compose --profile e2e run --rm e2e yarn test:e2e e2e/golden-path-fiscal.spec.ts
 *
 *  2. Headed on host machine (see the app run in a real browser):
 *       E2E_BASE_URL=http://localhost:8080 yarn test:e2e --headed
 *
 *  3. Interactive UI mode on host (full visual debugger in the browser):
 *       E2E_BASE_URL=http://localhost:8080 yarn test:e2e --ui
 *
 *  4. View traces / videos after any run:
 *       npx playwright show-report var/playwright-report   (opens http://localhost:9323)
 *       # or inside Docker:
 *       docker compose --profile e2e run --rm -p 9323:9323 e2e \
 *         yarn playwright show-report --host 0.0.0.0 var/playwright-report
 *
 * ── Environment variables ──────────────────────────────────────────────────
 *   E2E_BASE_URL    default: http://nubit-erp-web  (Docker) | http://localhost:8080 (host)
 *   E2E_USERNAME    optional; auto-seeded when omitted
 *   E2E_PASSWORD    optional; auto-seeded when omitted
 *   E2E_SALE_ID     optional; auto-seeded when omitted
 *
 * ── Note on Host header ────────────────────────────────────────────────────
 * We do NOT set extraHTTPHeaders.Host here. Chromium rejects page.goto() when
 * the Host header doesn't match the URL hostname (ERR_INVALID_ARGUMENT). The
 * app's Caddy server listens on :80 (any hostname), so no Host override is needed.
 */

const isCI = !!process.env.CI;

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',

  // Run tests sequentially — the happy-path is a stateful flow
  fullyParallel: false,
  workers: 1,

  // Retry once on CI to catch flaky selectors; no retries locally
  retries: isCI ? 1 : 0,

  // Show every test result line in the terminal
  reporter: [
    ['list'],
    // Always write an HTML report so you can open it after the run
    ['html', { outputFolder: 'var/playwright-report', open: 'never' }], // gitignored via /var/
  ],

  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',

    // The app resolves its language from the browser locale (Spanish-first
    // deployment). Pin it so selectors on Spanish labels don't depend on the
    // machine's locale (headless Chromium defaults to en-US).
    locale: 'es-PE',

    // ── Trace (step-by-step screenshots + network) ──────────────────────
    // 'on-first-retry' → only captured when a test fails and is retried.
    // Change to 'on' while debugging to always get a trace.
    trace: 'on-first-retry',

    // ── Video recording ─────────────────────────────────────────────────
    // 'retain-on-failure' → video is kept only when the best fails.
    // Change to 'on' while debugging to always record.
    video: 'retain-on-failure',

    // ── Screenshots ──────────────────────────────────────────────────────
    screenshot: 'only-on-failure',

    // Give each action a generous timeout for slow CI environments
    actionTimeout: 15_000,

    // Navigation timeout (waitForURL, goto, etc.)
    navigationTimeout: 30_000,

    // The PWA worker can retain a pre-restart auth cookie or old asset graph.
    // E2E validates the application against the live server, so block workers
    // to keep every browser context isolated and deterministic.
    serviceWorkers: 'block',
  },

  // Global timeout per test
  timeout: 120_000,

  // Output folder for traces, videos, screenshots
  outputDir: 'var/test-results',

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Launch args needed when running as root inside Docker
        launchOptions: {
          args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            // Disable Chrome autofill — prevents autofill from overriding Playwright fill()
            '--disable-features=AutofillServerCommunication,PasswordManagerUI',
          ],
        },
      },
    },
  ],
});
