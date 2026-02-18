// Playwright configuration for Supabase Bridge E2E tests
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',

  // Run tests in files in parallel
  fullyParallel: false,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Opt out of parallel tests on CI
  workers: 1,

  // Reporter to use
  reporter: [
    ['html'],
    ['json', { outputFile: 'test-results/results.json' }],
    ['list']
  ],

  // Shared settings for all the projects below
  use: {
    // Base URL to use in actions like `await page.goto('/')`
    baseURL: 'https://alexeykrol.com',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Screenshot on failure
    screenshot: 'only-on-failure',

    // Video on failure
    video: 'retain-on-failure',
  },

  // Configure projects for major browsers and devices
  projects: [
    // Desktop Browsers
    {
      name: 'chrome-desktop',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox-desktop',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'safari-desktop',
      use: { ...devices['Desktop Safari'] },
    },

    // Mobile Devices
    {
      name: 'iphone-14-pro',
      use: { ...devices['iPhone 14 Pro'] },
    },
    {
      name: 'samsung-galaxy-s21',
      use: { ...devices['Galaxy S21'] },
    },
    {
      name: 'ipad-pro',
      use: { ...devices['iPad Pro'] },
    },

    // Slow Connection Testing
    {
      name: 'slow-3g',
      use: {
        ...devices['Desktop Chrome'],
        // Simulate Slow 3G connection
        offline: false,
        httpCredentials: undefined,
      },
    },
  ],
});
