// @ts-check
import { test, expect, devices } from '@playwright/test';

/**
 * E2E Tests - Mobile Devices
 *
 * Tests auth flow on mobile devices (iPhone, Android)
 * Mobile-specific issues: touch events, viewport, keyboard
 */

test.describe('Mobile - iPhone 14 Pro', () => {
  test.use({ ...devices['iPhone 14 Pro'] });

  test('Auth page loads and displays correctly on iPhone', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Check viewport
    const viewport = page.viewportSize();
    expect(viewport?.width).toBeLessThanOrEqual(430); // iPhone width

    // OAuth buttons should be visible
    await expect(page.locator('#sb-google-btn')).toBeVisible();
    await expect(page.locator('#sb-facebook-btn')).toBeVisible();

    // Email input should be visible
    await expect(page.locator('#sb-email')).toBeVisible();
  });

  test('Google OAuth works on iPhone', async ({ page }) => {
    await page.goto('/test-no-elem/');

    await page.locator('#sb-google-btn').click();

    // Should redirect to Google
    await page.waitForURL(/accounts\.google\.com/, { timeout: 10000 });
    expect(page.url()).toContain('accounts.google.com');
  });

  test('Touch events work on mobile', async ({ page }) => {
    await page.goto('/test-no-elem/');

    // Tap email input
    await page.locator('#sb-email').tap();

    // Should focus
    const isFocused = await page.locator('#sb-email').evaluate(el =>
      el === document.activeElement
    );

    expect(isFocused).toBeTruthy();
  });
});

test.describe('Mobile - Samsung Galaxy S21', () => {
  test.use({ ...devices['Galaxy S21'] });

  test('Auth page loads on Android', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // Check Android viewport
    const viewport = page.viewportSize();
    expect(viewport?.width).toBeLessThanOrEqual(400); // Android width

    // All elements should be visible
    await expect(page.locator('#sb-google-btn')).toBeVisible();
    await expect(page.locator('#sb-facebook-btn')).toBeVisible();
    await expect(page.locator('#sb-email')).toBeVisible();
  });

  test('Facebook OAuth works on Android', async ({ page }) => {
    await page.goto('/test-no-elem/');

    await page.locator('#sb-facebook-btn').click();

    // Should redirect to Facebook
    await page.waitForURL(/facebook\.com/, { timeout: 10000 });
    expect(page.url()).toContain('facebook.com');
  });
});

test.describe('Mobile - iPad Pro', () => {
  test.use({ ...devices['iPad Pro'] });

  test('Auth page loads on iPad', async ({ page }) => {
    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');

    // iPad viewport is larger
    const viewport = page.viewportSize();
    expect(viewport?.width).toBeGreaterThan(700);

    // All auth options should be visible
    await expect(page.locator('#sb-google-btn')).toBeVisible();
    await expect(page.locator('#sb-facebook-btn')).toBeVisible();
  });
});
