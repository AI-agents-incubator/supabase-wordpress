// @ts-check
import { test, expect } from '@playwright/test';

/**
 * DEBUG TEST - Callback Page Investigation
 *
 * Simulates OAuth return from Google/Facebook
 * Tests what happens on /test-no-elem-2/ callback page
 */

test.describe('DEBUG - Callback Page Investigation', () => {
  let consoleMessages = [];
  let networkRequests = [];

  test.beforeEach(async ({ page }) => {
    consoleMessages = [];
    networkRequests = [];

    // Capture console
    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      consoleMessages.push({ type, text });
      console.log(`[CONSOLE ${type.toUpperCase()}] ${text}`);
    });

    // Capture network
    page.on('response', async response => {
      const request = response.request();

      if (response.url().includes('wp-json') ||
          response.url().includes('supabase') ||
          response.url().includes('callback')) {

        const entry = {
          method: request.method(),
          url: response.url(),
          status: response.status(),
          statusText: response.statusText()
        };

        networkRequests.push(entry);

        console.log(`\n📡 ${entry.method} ${entry.url}`);
        console.log(`   Status: ${entry.status} ${entry.statusText}`);

        // Log failed requests with body
        if (response.status() >= 400) {
          try {
            const body = await response.text();
            console.log(`   Response Body: ${body.substring(0, 1000)}`);
          } catch (e) {
            console.log(`   (Could not read response body)`);
          }
        }
      }
    });

    // Page errors
    page.on('pageerror', error => {
      console.log(`\n❌ PAGE ERROR: ${error.message}`);
    });
  });

  test('Callback Page - With Fake Google Token', async ({ page }) => {
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('🔍 DEBUG: Callback Page (Google OAuth Return)');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    // ========== Simulate Google OAuth Return ==========
    // After Google auth, user gets redirected to:
    // /test-no-elem-2/#access_token=xxx&refresh_token=yyy&token_type=bearer

    console.log('📄 STEP 1: Navigate to callback page WITH hash fragment...');
    console.log('   Simulating: User returned from Google OAuth\n');

    // Use a fake but valid-looking JWT structure
    const fakeAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
    const fakeRefreshToken = 'fake-refresh-token-123456';

    const callbackUrl = `/test-no-elem-2/#access_token=${fakeAccessToken}&refresh_token=${fakeRefreshToken}&token_type=bearer`;

    await page.goto(callbackUrl, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000); // Wait for callback.html to process

    console.log(`✅ Callback page loaded`);
    console.log(`   URL: ${page.url()}`);

    await page.screenshot({ path: 'test-results/debug-callback-step1.png', fullPage: true });

    // ========== Check Hash Fragment Extraction ==========
    console.log('\n🔍 STEP 2: Check if callback.html extracted tokens...');

    const hashInfo = await page.evaluate(() => {
      return {
        currentUrl: window.location.href,
        hash: window.location.hash,
        hasAccessToken: window.location.hash.includes('access_token'),
        hasError: window.location.hash.includes('error')
      };
    });

    console.log('   Hash Info:', JSON.stringify(hashInfo, null, 2));

    if (hashInfo.hasError) {
      console.log('❌ ERROR: URL contains error parameter');
    }

    if (!hashInfo.hasAccessToken) {
      console.log('❌ ERROR: Hash fragment does not contain access_token');
      console.log('   This means tokens were lost during navigation');
    }

    // ========== Check WordPress REST API Call ==========
    console.log('\n🔍 STEP 3: Check if WordPress REST API was called...');

    const wpApiCalls = networkRequests.filter(req =>
      req.url.includes('/wp-json/supabase-bridge/v1/callback')
    );

    if (wpApiCalls.length === 0) {
      console.log('❌ ERROR: NO WordPress REST API call detected');
      console.log('   callback.html did NOT send POST to /wp-json/supabase-bridge/v1/callback');
      console.log('   This means JavaScript in callback.html is broken or not running');
    } else {
      console.log(`✅ WordPress REST API called (${wpApiCalls.length} times)`);
      wpApiCalls.forEach((call, index) => {
        console.log(`\n   Call ${index + 1}:`);
        console.log(`   URL: ${call.url}`);
        console.log(`   Status: ${call.status} ${call.statusText}`);

        if (call.status === 403) {
          console.log('   ❌ CSRF PROTECTION BLOCKED THE REQUEST');
          console.log('   This is the bug! Referer header issue.');
        } else if (call.status === 401) {
          console.log('   ❌ JWT VERIFICATION FAILED');
          console.log('   (Expected - we used a fake token)');
        } else if (call.status === 200) {
          console.log('   ✅ Success! User should be logged in.');
        }
      });
    }

    // ========== Check Page State ==========
    console.log('\n🔍 STEP 4: Check page state after callback...');

    const pageState = await page.evaluate(() => {
      const instantLoader = document.getElementById('instant-loader');
      const authStatus = document.getElementById('authStatus');
      const errorVisible = document.querySelector('.sb-error, .auth-status.error');

      return {
        instantLoaderVisible: instantLoader && !instantLoader.classList.contains('hidden'),
        instantLoaderText: instantLoader ? instantLoader.textContent?.substring(0, 100) : null,
        authStatusText: authStatus ? authStatus.textContent : null,
        errorVisible: !!errorVisible,
        errorText: errorVisible ? errorVisible.textContent?.substring(0, 200) : null,
        currentUrl: window.location.href
      };
    });

    console.log('   Page State:', JSON.stringify(pageState, null, 2));

    if (pageState.errorVisible) {
      console.log(`\n❌ ERROR MESSAGE SHOWN TO USER:`);
      console.log(`   "${pageState.errorText}"`);
    }

    // ========== Console Analysis ==========
    console.log('\n🔍 STEP 5: Analyze console messages...');

    const errors = consoleMessages.filter(m => m.type === 'error');
    const warnings = consoleMessages.filter(m => m.type === 'warning');
    const logs = consoleMessages.filter(m => m.type === 'log');

    console.log(`   Total messages: ${consoleMessages.length}`);
    console.log(`   Errors: ${errors.length}`);
    console.log(`   Warnings: ${warnings.length}`);
    console.log(`   Logs: ${logs.length}`);

    if (errors.length > 0) {
      console.log('\n   ❌ ERRORS:');
      errors.forEach((err, i) => {
        console.log(`      ${i + 1}. ${err.text}`);
      });
    }

    // Look for specific callback logs
    const callbackLogs = logs.filter(l =>
      l.text.includes('[Auth Callback]') ||
      l.text.includes('Extracting tokens') ||
      l.text.includes('WordPress API') ||
      l.text.includes('CSRF') ||
      l.text.includes('JWT')
    );

    if (callbackLogs.length > 0) {
      console.log('\n   📋 CALLBACK LOGS:');
      callbackLogs.forEach((log, i) => {
        console.log(`      ${i + 1}. ${log.text}`);
      });
    }

    // ========== Final Summary ==========
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('📋 FINAL SUMMARY:');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    console.log(`Hash fragment preserved: ${hashInfo.hasAccessToken ? '✅ YES' : '❌ NO'}`);
    console.log(`WordPress API called: ${wpApiCalls.length > 0 ? '✅ YES' : '❌ NO'}`);
    console.log(`Page shows error: ${pageState.errorVisible ? '❌ YES' : '✅ NO'}`);
    console.log(`Console errors: ${errors.length}`);

    // Determine root cause
    console.log('\n🔍 ROOT CAUSE ANALYSIS:');

    if (!hashInfo.hasAccessToken) {
      console.log('   ❌ Tokens lost - Hash fragment not preserved');
      console.log('   → Check callback.html URL handling');
    } else if (wpApiCalls.length === 0) {
      console.log('   ❌ JavaScript not executing');
      console.log('   → Check callback.html script tags');
      console.log('   → Check browser console for JS errors');
    } else if (wpApiCalls.some(c => c.status === 403)) {
      console.log('   ❌ CSRF protection blocking requests');
      console.log('   → Fix already applied to supabase-bridge.php');
      console.log('   → Need to deploy to production');
    } else if (wpApiCalls.some(c => c.status === 401)) {
      console.log('   ℹ️  JWT verification failed (expected for fake token)');
      console.log('   → Real tokens from Google should work');
    } else if (wpApiCalls.some(c => c.status === 200)) {
      console.log('   ✅ Everything works!');
    }

    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    // Save report
    const fs = require('fs');
    const report = {
      timestamp: new Date().toISOString(),
      hashInfo,
      pageState,
      wpApiCalls,
      errors: errors.map(e => e.text),
      logs: callbackLogs.map(l => l.text)
    };

    fs.writeFileSync(
      'test-results/debug-callback-report.json',
      JSON.stringify(report, null, 2)
    );

    console.log('📄 Full report saved to: test-results/debug-callback-report.json\n');
  });

  test('Callback Page - WITHOUT Token (Error Case)', async ({ page }) => {
    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('🔍 DEBUG: Callback Page WITHOUT Token');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

    console.log('📄 Navigate to callback page without hash...');

    await page.goto('/test-no-elem-2/', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);

    const pageState = await page.evaluate(() => {
      const errorVisible = document.querySelector('.sb-error, .auth-status.error');
      return {
        errorVisible: !!errorVisible,
        errorText: errorVisible ? errorVisible.textContent : null
      };
    });

    console.log(`\nError shown to user: ${pageState.errorVisible ? 'YES' : 'NO'}`);
    if (pageState.errorText) {
      console.log(`Error message: "${pageState.errorText}"`);
    }

    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
  });
});
