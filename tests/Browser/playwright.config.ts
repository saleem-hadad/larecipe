import { defineConfig, devices } from '@playwright/test'
import { resolve } from 'node:path'

/**
 * Browser tests for the reading experience, against the Testbench workbench
 * (vendor/bin/testbench serve). The "guide" doc set (tests/Fixtures/track-b)
 * holds every component in its exact server HTML.
 *
 *   pnpm test:browser
 */
const root = resolve(__dirname, '../..')
const port = Number(process.env.LARECIPE_PORT ?? 8771)

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.ts',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : 4,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never', outputFolder: resolve(root, 'playwright-report') }]] : [['list']],
  outputDir: resolve(root, 'test-results/browser'),
  globalSetup: resolve(__dirname, 'support/global-setup.ts'),
  timeout: 30_000,
  expect: { timeout: 7_000 },
  use: {
    baseURL: `http://127.0.0.1:${port}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } }],
  webServer: {
    command: `vendor/bin/testbench serve --port=${port}`,
    cwd: root,
    url: `http://127.0.0.1:${port}/guide/3.x`,
    reuseExistingServer: !process.env.CI,
    timeout: 60_000,
    stdout: 'ignore',
    stderr: 'pipe',
    // The PHP dev server answers one request at a time unless it has workers.
    env: { PHP_CLI_SERVER_WORKERS: '6' },
  },
})
