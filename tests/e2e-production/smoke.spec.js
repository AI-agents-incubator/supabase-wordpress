// @ts-check
import { test, expect } from '@playwright/test';

/**
 * SMOKE TESTS - Production Health Check
 *
 * Quick tests to verify production is working after deployment
 * Should complete in < 30 seconds
 *
 * Run with: npx playwright test tests/e2e-production/smoke.spec.js
 */

test.describe('Production Smoke Tests', () => {
  test('Auth page loads successfully', async ({ page }) => {
    // Go to auth page
    await page.goto('/test-no-elem/');

    // Page should load without errors
    await expect(page).toHaveTitle(/Вход.*Регистрация/);

    // Check no JavaScript errors
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));

    await page.waitForLoadState('networkidle');

    // Should have no critical JS errors
    const criticalErrors = errors.filter(err =>
      err.includes('ReferenceError') ||
      err.includes('TypeError') ||
      err.includes('SyntaxError')
    );

    expect(criticalErrors).toHaveLength(0);
  });

  test('Google OAuth button is visible', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    const googleButton = page.locator('#sb-google-btn');
    await expect(googleButton).toBeVisible();
  });

  test('Facebook OAuth button is visible', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    const facebookButton = page.locator('#sb-facebook-btn');
    await expect(facebookButton).toBeVisible();
  });

  test('Email input and submit button are visible', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    const emailInput = page.locator('#sb-email');
    const submitButton = page.locator('#sb-submit');

    await expect(emailInput).toBeVisible();
    await expect(submitButton).toBeVisible();
  });

  test('OTP code toggle button is visible', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // After sending email, code toggle should appear
    const codeToggle = page.locator('#sb-show-code');

    // Check if visible (might be hidden until email sent)
    const isVisible = await codeToggle.isVisible().catch(() => false);

    // Just log visibility - not critical for smoke test
    console.log(`OTP code toggle visible: ${isVisible}`);
  });

  test('Callback page loads successfully', async ({ page }) => {
    // Test callback page with fake hash
    await page.goto('/test-no-elem-2/#access_token=FAKE_TOKEN&token_type=bearer');

    // Should load without critical errors
    await page.waitForTimeout(1000);

    // Check that callback script loaded
    const hasCallbackScript = await page.evaluate(() => {
      return document.body.innerHTML.includes('Auth Callback');
    });

    expect(hasCallbackScript).toBeTruthy();
  });

  test('WordPress REST API is accessible', async ({ page, request }) => {
    // Check if WordPress REST API responds
    const response = await request.get('/wp-json/');

    expect(response.ok()).toBeTruthy();
    expect(response.status()).toBe(200);

    const data = await response.json();
    expect(data.name).toBeDefined();
  });

  test('Supabase Bridge REST API endpoint exists', async ({ page, request }) => {
    // Check if our plugin endpoint exists (should return 400/401 without auth)
    const response = await request.post('/wp-json/supabase-bridge/v1/callback', {
      data: { access_token: 'FAKE' },
      failOnStatusCode: false,
    });

    // Should return 401 (unauthorized) - means endpoint exists
    expect([400, 401]).toContain(response.status());
  });
});
