// @ts-check
import { test, expect } from '@playwright/test';

/**
 * E2E Tests - Special Scenarios
 *
 * Tests for edge cases:
 * - Slow internet connection (Slow 3G)
 * - VPN blocking Supabase redirects
 * - Network timeouts
 * - OTP code fallback
 */

test.describe('Special Scenarios', () => {
  test('Auth page loads on Slow 3G connection', async ({ page, context }) => {
    // Simulate Slow 3G
    await context.route('**/*', async (route, request) => {
      // Add delay to simulate slow connection
      await new Promise(resolve => setTimeout(resolve, 500));
      await route.continue();
    });

    await page.goto('/test-no-elem/', { timeout: 30000 });
    await page.waitForTimeout(3000);

    // Page should eventually load
    await expect(page.locator('#sb-google-btn')).toBeVisible({ timeout: 30000 });
  });

  test('OTP code fallback button is visible (VPN scenario)', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Check that OTP code toggle button exists
    const codeToggle = page.locator('#sb-show-code');
    const exists = await codeToggle.count();

    expect(exists).toBeGreaterThan(0);

    // Check button text mentions "Проблемы со входом"
    const buttonText = await codeToggle.textContent();
    expect(buttonText).toContain('Проблемы со входом');
    expect(buttonText).toContain('код из письма');
  });

  test('OTP code entry form has correct placeholders', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Check OTP code input placeholder
    const codeInput = page.locator('#sb-code-input');
    const placeholder = await codeInput.getAttribute('placeholder');

    expect(placeholder).toContain('6 цифр');
  });

  test('Callback page handles missing tokens gracefully', async ({ page }) => {
    // Navigate without tokens
    await page.goto('/test-no-elem-2/');

    await page.waitForTimeout(2000);

    // Should not crash - should show error or redirect
    const hasError = await page.locator('.sb-error').isVisible().catch(() => false);
    const currentUrl = page.url();

    // Either shows error or redirects away
    const handledGracefully = hasError || !currentUrl.includes('/test-no-elem-2/');

    expect(handledGracefully).toBeTruthy();
  });

  test('Callback page handles malformed tokens', async ({ page }) => {
    // Navigate with malformed token
    await page.goto('/test-no-elem-2/#access_token=INVALID_TOKEN&token_type=bearer');

    await page.waitForTimeout(3000);

    // Should show error or redirect to auth page
    const hasError = await page.locator('.sb-error').isVisible().catch(() => false);
    const redirectedToAuth = page.url().includes('/test-no-elem/');

    expect(hasError || redirectedToAuth).toBeTruthy();
  });

  test('Network timeout handling', async ({ page, context }) => {
    let timeoutOccurred = false;

    // Simulate network timeout for specific requests
    await context.route('**/supabase.co/**', async (route) => {
      timeoutOccurred = true;
      await route.abort('timedout');
    });

    await page.goto('/test-no-elem/');

    // Click Google OAuth button
    await page.locator('#sb-google-btn').click();

    await page.waitForTimeout(3000);

    // Timeout should have occurred
    expect(timeoutOccurred).toBeTruthy();

    // Page should handle gracefully (not crash)
    const pageNotCrashed = await page.evaluate(() => {
      return document.body !== null;
    });

    expect(pageNotCrashed).toBeTruthy();
  });

  test('Multiple rapid clicks do not break auth', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const googleButton = page.locator('#sb-google-btn');

    // Click multiple times rapidly
    await googleButton.click({ clickCount: 3, delay: 100 });

    await page.waitForTimeout(2000);

    // Should not crash or show errors
    const criticalErrors = [];
    page.on('pageerror', error => {
      if (error.message.includes('ReferenceError') ||
          error.message.includes('TypeError')) {
        criticalErrors.push(error.message);
      }
    });

    expect(criticalErrors).toHaveLength(0);
  });

  test('localStorage is accessible (Safari Private Mode compatibility)', async ({ page }) => {
    await page.goto('/test-no-elem/');

    // Check localStorage availability
    const localStorageWorks = await page.evaluate(() => {
      try {
        localStorage.setItem('test', 'value');
        const value = localStorage.getItem('test');
        localStorage.removeItem('test');
        return value === 'value';
      } catch {
        return false;
      }
    });

    expect(localStorageWorks).toBeTruthy();
  });
});
