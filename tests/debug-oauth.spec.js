// @ts-check
import { test, expect } from '@playwright/test';

/**
 * DEBUG TEST - OAuth Flow Investigation
 *
 * This test captures EVERYTHING happening during OAuth flow:
 * - All network requests (with headers, status, body)
 * - All console messages (errors, warnings, logs)
 * - Screenshots at each step
 * - URL changes and redirects
 */

test.describe('DEBUG - OAuth Flow Investigation', () => {
  let consoleMessages = [];
  let networkRequests = [];
  let networkFailures = [];

  test.beforeEach(async ({ page }) => {
    // Reset arrays
    consoleMessages = [];
    networkRequests = [];
    networkFailures = [];

    // Capture ALL console messages
    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      consoleMessages.push({ type, text, timestamp: new Date().toISOString() });

      console.log(`[CONSOLE ${type.toUpperCase()}] ${text}`);
    });

    // Capture ALL network requests
    page.on('request', request => {
      networkRequests.push({
        type: 'request',
        method: request.method(),
        url: request.url(),
        headers: request.headers(),
        timestamp: new Date().toISOString()
      });
    });

    // Capture ALL network responses
    page.on('response', async response => {
      const request = response.request();

      networkRequests.push({
        type: 'response',
        method: request.method(),
        url: response.url(),
        status: response.status(),
        statusText: response.statusText(),
        headers: response.headers(),
        timestamp: new Date().toISOString()
      });

      // Log failed requests
      if (response.status() >= 400) {
        console.log(`\n❌ FAILED REQUEST: ${request.method()} ${response.url()}`);
        console.log(`   Status: ${response.status()} ${response.statusText()}`);

        try {
          const body = await response.text();
          console.log(`   Response Body: ${body.substring(0, 500)}`);
        } catch (e) {
          console.log(`   (Could not read response body)`);
        }
      }
    });

    // Capture network failures
    page.on('requestfailed', request => {
      const failure = {
        url: request.url(),
        method: request.method(),
        failure: request.failure()?.errorText,
        timestamp: new Date().toISOString()
      };

      networkFailures.push(failure);
      console.log(`\n❌ NETWORK FAILURE: ${request.method()} ${request.url()}`);
      console.log(`   Error: ${failure.failure}`);
    });

    // Capture page errors
    page.on('pageerror', error => {
      console.log(`\n❌ PAGE ERROR: ${error.message}`);
      console.log(`   Stack: ${error.stack}`);
    });
  });

  test('Google OAuth Flow - Complete Debug', async ({ page }) => {
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('🔍 DEBUG: Starting OAuth Flow Investigation');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    // ========== STEP 1: Load Login Page ==========
    console.log('\n📄 STEP 1: Loading login page...');
    await page.goto('/test-no-elem/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);

    await page.screenshot({ path: 'test-results/debug-step1-login-page.png', fullPage: true });
    console.log('✅ Login page loaded');
    console.log(`   URL: ${page.url()}`);

    // ========== STEP 2: Check if Google Button Exists ==========
    console.log('\n🔍 STEP 2: Checking Google OAuth button...');
    const googleBtn = page.locator('#sb-google-btn');
    const googleBtnExists = await googleBtn.count();

    if (googleBtnExists === 0) {
      console.log('❌ ERROR: Google OAuth button NOT FOUND');
      console.log('   Selector: #sb-google-btn');

      // Dump all buttons on page
      const allButtons = await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button, a[role="button"]'));
        return buttons.map(btn => ({
          id: btn.id || 'NO_ID',
          className: btn.className,
          text: btn.textContent?.trim().substring(0, 50)
        }));
      });

      console.log('   Available buttons on page:', JSON.stringify(allButtons, null, 2));
      throw new Error('Google OAuth button not found');
    }

    console.log('✅ Google OAuth button found');
    const buttonText = await googleBtn.textContent();
    console.log(`   Button text: "${buttonText}"`);

    // ========== STEP 3: Check Supabase Client ==========
    console.log('\n🔍 STEP 3: Checking Supabase client...');
    const supabaseStatus = await page.evaluate(() => {
      return {
        supabaseClientExists: typeof window.supabaseClient !== 'undefined',
        supabaseCfgExists: typeof window.SUPABASE_CFG !== 'undefined',
        supabaseLibExists: typeof window.supabase !== 'undefined'
      };
    });

    console.log('   Supabase Status:', JSON.stringify(supabaseStatus, null, 2));

    if (!supabaseStatus.supabaseClientExists) {
      console.log('❌ ERROR: window.supabaseClient not initialized');
    }

    // ========== STEP 4: Click Google OAuth Button ==========
    console.log('\n🔍 STEP 4: Clicking Google OAuth button...');

    // Clear network logs before click
    networkRequests = [];

    await googleBtn.click();
    console.log('✅ Button clicked');

    await page.screenshot({ path: 'test-results/debug-step4-after-click.png', fullPage: true });

    // ========== STEP 5: Wait for Redirect ==========
    console.log('\n🔍 STEP 5: Waiting for redirect...');

    try {
      // Wait up to 10 seconds for redirect to Google OR error
      await page.waitForFunction(() => {
        const url = window.location.href;
        return url.includes('accounts.google.com') ||
               url.includes('error') ||
               document.querySelector('.sb-error');
      }, { timeout: 10000 });

      const currentUrl = page.url();
      console.log(`✅ Page changed to: ${currentUrl}`);

      if (currentUrl.includes('accounts.google.com')) {
        console.log('✅ SUCCESS: Redirected to Google OAuth');
        console.log('   OAuth flow is working correctly');
      } else if (currentUrl.includes('error')) {
        console.log('❌ ERROR: Redirected with error in URL');
      } else {
        console.log('⚠️  WARNING: Page changed but not to Google');

        // Check for error message on page
        const errorMsg = await page.locator('.sb-error').textContent().catch(() => null);
        if (errorMsg) {
          console.log(`   Error message: ${errorMsg}`);
        }
      }

    } catch (e) {
      console.log('❌ ERROR: No redirect happened within 10 seconds');
      console.log(`   Current URL: ${page.url()}`);

      // Check for error message
      const errorMsg = await page.locator('.sb-error').textContent().catch(() => null);
      if (errorMsg) {
        console.log(`   Error message on page: ${errorMsg}`);
      }

      await page.screenshot({ path: 'test-results/debug-step5-no-redirect.png', fullPage: true });
    }

    // ========== STEP 6: Analyze Network Requests ==========
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📊 NETWORK REQUESTS AFTER BUTTON CLICK:');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    // Filter requests after button click
    const relevantRequests = networkRequests.filter(req => {
      const url = req.url;
      return url.includes('supabase') ||
             url.includes('google') ||
             url.includes('auth') ||
             url.includes('/test-no-elem');
    });

    relevantRequests.forEach((req, index) => {
      console.log(`\n${index + 1}. ${req.type.toUpperCase()}: ${req.method} ${req.url}`);
      if (req.status) {
        console.log(`   Status: ${req.status} ${req.statusText}`);
      }
      if (req.type === 'request' && req.method === 'POST') {
        console.log(`   Headers:`, JSON.stringify(req.headers, null, 2));
      }
    });

    // ========== STEP 7: Analyze Console Messages ==========
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📊 CONSOLE MESSAGES:');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    const errors = consoleMessages.filter(m => m.type === 'error');
    const warnings = consoleMessages.filter(m => m.type === 'warning');

    console.log(`Total messages: ${consoleMessages.length}`);
    console.log(`Errors: ${errors.length}`);
    console.log(`Warnings: ${warnings.length}`);

    if (errors.length > 0) {
      console.log('\n❌ ERRORS:');
      errors.forEach((err, i) => {
        console.log(`   ${i + 1}. ${err.text}`);
      });
    }

    if (warnings.length > 0) {
      console.log('\n⚠️  WARNINGS:');
      warnings.forEach((warn, i) => {
        console.log(`   ${i + 1}. ${warn.text}`);
      });
    }

    // ========== STEP 8: Final Summary ==========
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📋 FINAL SUMMARY:');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    const finalUrl = page.url();
    const redirectSuccess = finalUrl.includes('accounts.google.com');

    console.log(`Final URL: ${finalUrl}`);
    console.log(`Google OAuth redirect: ${redirectSuccess ? '✅ SUCCESS' : '❌ FAILED'}`);
    console.log(`Console errors: ${errors.length}`);
    console.log(`Network failures: ${networkFailures.length}`);

    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    // Write full debug report to file
    const fs = require('fs');
    const debugReport = {
      timestamp: new Date().toISOString(),
      finalUrl,
      redirectSuccess,
      consoleMessages,
      networkRequests: relevantRequests,
      networkFailures,
      supabaseStatus
    };

    fs.writeFileSync(
      'test-results/debug-oauth-report.json',
      JSON.stringify(debugReport, null, 2)
    );

    console.log('📄 Full debug report saved to: test-results/debug-oauth-report.json\n');
  });
});
