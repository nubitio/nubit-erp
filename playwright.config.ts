import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://web',
    extraHTTPHeaders: {
      Host: process.env.E2E_HOST_HEADER ?? 'localhost',
    },
  },
});
