// @ts-check
import { test, expect } from '@playwright/test';

/**
 * DIAGNOSTIC TEST - Authentication Flow Debugging
 *
 * This test captures:
 * - Console errors
 * - Network failures
 * - JavaScript exceptions
 * - Redirect behavior
 *
 * Run with: npx playwright test --headed
 */

test.describe('Auth Flow Diagnostics', () => {
  let consoleMessages = [];
  let networkErrors = [];
  let pageErrors = [];

  test.beforeEach(async ({ page }) => {
    // Clear diagnostic arrays
    consoleMessages = [];
    networkErrors = [];
    pageErrors = [];

    // Capture console messages
    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      consoleMessages.push({ type, text });

      // Print to terminal immediately
      if (type === 'error') {
        console.log(`🔴 Console Error: ${text}`);
      } else if (type === 'warning') {
        console.log(`⚠️  Console Warning: ${text}`);
      }
    });

    // Capture network failures
    page.on('requestfailed', request => {
      const failure = {
        url: request.url(),
        method: request.method(),
        failure: request.failure()
      };
      networkErrors.push(failure);
      console.log(`❌ Network Failed: ${request.method()} ${request.url()}`);
      console.log(`   Reason: ${request.failure()?.errorText}`);
    });

    // Capture JavaScript errors
    page.on('pageerror', error => {
      pageErrors.push(error.message);
      console.log(`💥 JavaScript Error: ${error.message}`);
    });
  });

  test.afterEach(async () => {
    // Print diagnostic summary
    console.log('\n' + '='.repeat(60));
    console.log('DIAGNOSTIC SUMMARY');
    console.log('='.repeat(60));

    console.log(`\n📊 Console Messages: ${consoleMessages.length}`);
    consoleMessages.forEach(({ type, text }) => {
      console.log(`  [${type}] ${text}`);
    });

    console.log(`\n🌐 Network Errors: ${networkErrors.length}`);
    networkErrors.forEach(({ method, url, failure }) => {
      console.log(`  ${method} ${url}`);
      console.log(`    → ${failure?.errorText || 'Unknown error'}`);
    });

    console.log(`\n💥 JavaScript Errors: ${pageErrors.length}`);
    pageErrors.forEach(error => {
      console.log(`  ${error}`);
    });

    console.log('\n' + '='.repeat(60) + '\n');
  });

  test('Google OAuth Flow - Diagnostic Mode', async ({ page }) => {
    console.log('\n🔍 Starting Google OAuth diagnostic...\n');

    // Go to auth page
    console.log('📄 Loading auth page...');
    await page.goto('/test-no-elem/');

    // Wait for page to load
    await page.waitForLoadState('networkidle');
    console.log('✅ Page loaded');

    // Take screenshot
    await page.screenshot({ path: 'tests/screenshots/01-auth-page.png', fullPage: true });
    console.log('📸 Screenshot saved: tests/screenshots/01-auth-page.png');

    // Check if Google button exists
    const googleButton = page.locator('#sb-google-btn');
    const isVisible = await googleButton.isVisible();
    console.log(`🔘 Google button visible: ${isVisible}`);

    if (!isVisible) {
      console.log('❌ Google OAuth button not found!');
      return;
    }

    // Click Google OAuth button and wait for navigation
    console.log('🖱️  Clicking Google OAuth button...');

    // Listen for popup (OAuth may open in popup)
    const popupPromise = page.waitForEvent('popup', { timeout: 5000 }).catch(() => null);

    // Click button
    await googleButton.click();

    // Check if popup opened
    const popup = await popupPromise;
    if (popup) {
      console.log('🪟 OAuth opened in popup window');
      console.log(`   URL: ${popup.url()}`);
      await popup.screenshot({ path: 'tests/screenshots/02-oauth-popup.png', fullPage: true });
    } else {
      console.log('↗️  No popup - checking main page navigation...');

      // Wait a bit for any redirect
      await page.waitForTimeout(2000);

      console.log(`   Current URL: ${page.url()}`);
      await page.screenshot({ path: 'tests/screenshots/02-after-click.png', fullPage: true });
    }

    // Wait to capture any delayed errors
    await page.waitForTimeout(3000);
  });

  test('Magic Link Flow - Diagnostic Mode', async ({ page }) => {
    console.log('\n🔍 Starting Magic Link diagnostic...\n');

    await page.goto('/test-no-elem/');
    await page.waitForLoadState('networkidle');
    console.log('✅ Auth page loaded');

    // Check if email input exists
    const emailInput = page.locator('#sb-email');
    const submitButton = page.locator('#sb-submit');

    const emailVisible = await emailInput.isVisible();
    const buttonVisible = await submitButton.isVisible();

    console.log(`📧 Email input visible: ${emailVisible}`);
    console.log(`🔘 Submit button visible: ${buttonVisible}`);

    if (!emailVisible || !buttonVisible) {
      console.log('❌ Magic Link form not found!');
      return;
    }

    // Fill email
    console.log('📝 Filling test email...');
    await emailInput.fill('test@example.com');
    await page.screenshot({ path: 'tests/screenshots/03-email-filled.png', fullPage: true });

    // Click submit
    console.log('🖱️  Clicking submit button...');
    await submitButton.click();

    // Wait for response
    await page.waitForTimeout(2000);

    console.log(`   Current URL: ${page.url()}`);
    await page.screenshot({ path: 'tests/screenshots/04-after-submit.png', fullPage: true });

    // Check for success/error messages
    const successMsg = await page.locator('.sb-success').isVisible().catch(() => false);
    const errorMsg = await page.locator('.sb-error').isVisible().catch(() => false);

    console.log(`✅ Success message visible: ${successMsg}`);
    console.log(`❌ Error message visible: ${errorMsg}`);

    if (errorMsg) {
      const errorText = await page.locator('.sb-error').textContent();
      console.log(`   Error text: ${errorText}`);
    }
  });

  test('Callback Page - Direct Access Test', async ({ page }) => {
    console.log('\n🔍 Testing callback page directly...\n');

    // Test callback page with fake hash
    const fakeHash = '#access_token=FAKE_TOKEN_TEST&token_type=bearer';
    const callbackUrl = `/test-no-elem-2/${fakeHash}`;

    console.log(`📄 Loading: ${callbackUrl}`);
    await page.goto(callbackUrl);

    await page.waitForTimeout(2000);

    console.log(`   Current URL: ${page.url()}`);
    console.log(`   Hash preserved: ${page.url().includes('#access_token')}`);

    await page.screenshot({ path: 'tests/screenshots/05-callback-test.png', fullPage: true });
  });
});
