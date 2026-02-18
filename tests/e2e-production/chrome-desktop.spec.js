// @ts-check
import { test, expect } from '@playwright/test';

/**
 * E2E Tests - Chrome Desktop
 *
 * Full user journey testing on Chrome Desktop
 * Tests Google OAuth, Facebook OAuth, and Magic Link flows
 */

test.use({ ...test.use(), browserName: 'chromium' });

test.describe('Chrome Desktop - User Journeys', () => {
  let consoleErrors = [];
  let networkFailures = [];

  test.beforeEach(async ({ page }) => {
    // Reset diagnostic arrays
    consoleErrors = [];
    networkFailures = [];

    // Capture console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // Capture network failures
    page.on('requestfailed', request => {
      networkFailures.push({
        url: request.url(),
        method: request.method(),
        failure: request.failure()?.errorText,
      });
    });
  });

  test.afterEach(async () => {
    // Log diagnostics if test failed
    if (consoleErrors.length > 0) {
      console.log('\n🔴 Console Errors:', consoleErrors);
    }
    if (networkFailures.length > 0) {
      console.log('\n❌ Network Failures:', networkFailures);
    }
  });

  test('Google OAuth button triggers redirect', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Click Google OAuth button
    const googleButton = page.locator('#sb-google-btn');
    await expect(googleButton).toBeVisible();

    await googleButton.click();

    // Should redirect to Google OAuth
    await page.waitForURL(/accounts\.google\.com/, { timeout: 10000 });

    expect(page.url()).toContain('accounts.google.com');
    expect(page.url()).toContain('client_id');
    expect(page.url()).toContain('redirect_uri');

    // Check redirect_uri contains our callback URL
    const url = new URL(page.url());
    const redirectUri = url.searchParams.get('redirect_uri');

    expect(redirectUri).toContain('supabase.co/auth/v1/callback');
  });

  test('Facebook OAuth button triggers redirect', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Click Facebook OAuth button
    const facebookButton = page.locator('#sb-facebook-btn');
    await expect(facebookButton).toBeVisible();

    await facebookButton.click();

    // Should redirect to Facebook OAuth
    await page.waitForURL(/facebook\.com/, { timeout: 10000 });

    expect(page.url()).toContain('facebook.com');
  });

  test('Magic Link email submission shows success message', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Fill email
    const emailInput = page.locator('#sb-email-input');
    await emailInput.fill('test@example.com');

    // Click submit
    const submitButton = page.locator('button[type="submit"]');
    await submitButton.click();

    // Wait for response
    await page.waitForTimeout(2000);

    // Should show either success message or email sent screen
    const successVisible = await page.locator('.sb-success').isVisible().catch(() => false);
    const emailSentVisible = await page.locator('.sb-code-toggle').isVisible().catch(() => false);

    // One of these should be true
    expect(successVisible || emailSentVisible).toBeTruthy();
  });

  test('OTP code entry button is visible after deployment', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Check if OTP code toggle button exists in DOM
    const codeToggleButton = page.locator('#sb-show-code');
    const exists = await codeToggleButton.count();

    expect(exists).toBeGreaterThan(0);

    // Check button text
    const buttonText = await codeToggleButton.textContent();
    expect(buttonText).toContain('Проблемы со входом');
  });

  test('Callback page handles hash fragment correctly', async ({ page }) => {
    // Navigate to callback with fake token
    await page.goto('/test-no-elem-2/#access_token=FAKE_TOKEN_TEST&token_type=bearer');

    await page.waitForTimeout(2000);

    // Check that hash is preserved
    expect(page.url()).toContain('#access_token=FAKE_TOKEN_TEST');

    // Check that callback script extracted token
    const logs = await page.evaluate(() => {
      // Check if callback script logged token extraction
      return window.localStorage.getItem('last_auth_attempt') || 'no attempt';
    });

    // Callback should have attempted to process the token
    console.log('Last auth attempt:', logs);
  });

  test('No critical JavaScript errors on auth page', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    await page.waitForTimeout(2000);

    // Filter for critical errors only
    const criticalErrors = consoleErrors.filter(err =>
      err.includes('ReferenceError') ||
      err.includes('TypeError') ||
      err.includes('SyntaxError') ||
      err.includes('is not defined')
    );

    expect(criticalErrors).toHaveLength(0);
  });

  test('Supabase client initializes successfully', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Check that Supabase client is initialized
    const supabaseInitialized = await page.evaluate(() => {
      return typeof window.supabaseClient !== 'undefined';
    });

    expect(supabaseInitialized).toBeTruthy();
  });
});
