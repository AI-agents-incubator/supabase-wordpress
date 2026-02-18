# Testing Examples

**Practical examples and common workflows**

---

## 🎯 Common Workflows

### **Scenario 1: Fix a Bug**

```bash
# 1. Make code changes
vim auth-form.html

# 2. Check what's out of sync
npm run sync

# Output:
# ✅ supabase-bridge.php - SYNCED
# ⚠️  auth-form.html - OUT OF SYNC

# 3. Deploy and test automatically
npm run deploy:test auth-form.html

# If tests pass → Done!
# If tests fail → Automatic rollback
```

---

### **Scenario 2: Deploy Multiple Files**

```bash
# Check all out-of-sync files
npm run sync

# Deploy specific files
./scripts/deploy.sh auth-form.html callback.html supabase-bridge.php

# Run smoke tests manually
npm run test:smoke
```

---

### **Scenario 3: Test Before Deploy**

```bash
# 1. Make changes locally
# ...

# 2. Run smoke tests (fast check)
npm run test:smoke

# 3. Run full E2E tests
npm run test:all

# 4. If all pass → deploy
npm run deploy
```

---

### **Scenario 4: Emergency Rollback**

```bash
# Something went wrong in production
# Rollback to last backup
./scripts/rollback.sh

# Verify rollback worked
npm run test:smoke
```

---

### **Scenario 5: Test on Specific Platform**

```bash
# Test only on iPhone
npx playwright test --project=iphone-14-pro

# Test only on Android
npx playwright test --project=samsung-galaxy-s21

# Test mobile only
npm run test:mobile
```

---

## 📝 Example Test Cases

### **Example 1: Test Google OAuth Flow**

```javascript
import { test, expect } from '@playwright/test';

test('Google OAuth redirects correctly', async ({ page }) => {
  // 1. Go to auth page
  await page.goto('/test-no-elem/');
  await page.waitForLoadState('networkidle');

  // 2. Click Google OAuth button
  const googleBtn = page.locator('#sb-google-btn');
  await expect(googleBtn).toBeVisible();
  await googleBtn.click();

  // 3. Verify redirect to Google
  await page.waitForURL(/accounts\.google\.com/, { timeout: 10000 });

  // 4. Check URL parameters
  expect(page.url()).toContain('client_id');
  expect(page.url()).toContain('redirect_uri');
  expect(page.url()).toContain('supabase.co');
});
```

---

### **Example 2: Test Magic Link Submission**

```javascript
test('Magic Link email submission works', async ({ page }) => {
  await page.goto('/test-no-elem/');

  // Fill email
  await page.fill('#sb-email', 'test@example.com');

  // Click submit
  await page.click('#sb-submit');

  // Wait for response
  await page.waitForTimeout(2000);

  // Check for success message or email sent screen
  const successMsg = await page.locator('.sb-success').isVisible();
  const emailSent = await page.locator('.sb-code-toggle').isVisible();

  expect(successMsg || emailSent).toBeTruthy();
});
```

---

### **Example 3: Test OTP Code Button Visibility**

```javascript
test('OTP code fallback button is visible', async ({ page }) => {
  await page.goto('/test-no-elem/');
  await page.waitForLoadState('networkidle');

  // Check button exists
  const codeToggle = page.locator('#sb-show-code');
  await expect(codeToggle).toBeVisible();

  // Check button text
  const text = await codeToggle.textContent();
  expect(text).toContain('Проблемы со входом');
  expect(text).toContain('код из письма');
});
```

---

### **Example 4: Test Callback Page**

```javascript
test('Callback handles tokens correctly', async ({ page }) => {
  // Navigate with fake token
  await page.goto('/test-no-elem-2/#access_token=FAKE_TOKEN&token_type=bearer');

  await page.waitForTimeout(2000);

  // Hash should be preserved
  expect(page.url()).toContain('#access_token=FAKE_TOKEN');

  // Should attempt authentication
  const attempted = await page.evaluate(() => {
    return localStorage.getItem('last_auth_attempt') !== null;
  });

  expect(attempted).toBeTruthy();
});
```

---

### **Example 5: Test Error Handling**

```javascript
test('Handles malformed tokens gracefully', async ({ page }) => {
  // Navigate with invalid token
  await page.goto('/test-no-elem-2/#access_token=INVALID&token_type=bearer');

  await page.waitForTimeout(3000);

  // Should show error or redirect
  const hasError = await page.locator('.sb-error').isVisible();
  const redirected = page.url().includes('/test-no-elem/');

  expect(hasError || redirected).toBeTruthy();
});
```

---

### **Example 6: Test Mobile Touch Events**

```javascript
import { devices } from '@playwright/test';

test.use({ ...devices['iPhone 14 Pro'] });

test('Touch events work on mobile', async ({ page }) => {
  await page.goto('/test-no-elem/');

  // Tap email input
  await page.locator('#sb-email').tap();

  // Check if focused
  const isFocused = await page.locator('#sb-email').evaluate(el =>
    el === document.activeElement
  );

  expect(isFocused).toBeTruthy();
});
```

---

### **Example 7: Test Network Timeout**

```javascript
test('Handles network timeout gracefully', async ({ page, context }) => {
  // Simulate timeout
  await context.route('**/supabase.co/**', async (route) => {
    await route.abort('timedout');
  });

  await page.goto('/test-no-elem/');

  // Click OAuth button
  await page.locator('#sb-google-btn').click();

  await page.waitForTimeout(3000);

  // Page should not crash
  const pageAlive = await page.evaluate(() => document.body !== null);
  expect(pageAlive).toBeTruthy();
});
```

---

### **Example 8: Test Slow Connection**

```javascript
test('Works on slow connection', async ({ page, context }) => {
  // Add delay to all requests
  await context.route('**/*', async (route) => {
    await new Promise(resolve => setTimeout(resolve, 1000));
    await route.continue();
  });

  await page.goto('/test-no-elem/', { timeout: 30000 });

  // Page should eventually load
  await expect(page.locator('#sb-google-btn')).toBeVisible({ timeout: 30000 });
});
```

---

## 🎭 Custom Test Helpers

### **Helper: Login via Google OAuth**

```javascript
async function loginViaGoogle(page) {
  await page.goto('/test-no-elem/');
  await page.locator('#sb-google-btn').click();

  // Wait for Google page
  await page.waitForURL(/accounts\.google\.com/);

  // Note: Full OAuth requires real credentials
  // For testing, we check redirect works correctly
  return page.url();
}

test('Login via Google', async ({ page }) => {
  const googleUrl = await loginViaGoogle(page);
  expect(googleUrl).toContain('accounts.google.com');
});
```

---

### **Helper: Check Console Errors**

```javascript
async function captureConsoleErrors(page) {
  const errors = [];

  page.on('console', msg => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });

  return errors;
}

test('No console errors on auth page', async ({ page }) => {
  const errors = await captureConsoleErrors(page);

  await page.goto('/test-no-elem/');
  await page.waitForLoadState('networkidle');

  // Filter critical errors
  const critical = errors.filter(err =>
    err.includes('ReferenceError') ||
    err.includes('TypeError') ||
    err.includes('SyntaxError')
  );

  expect(critical).toHaveLength(0);
});
```

---

### **Helper: Check Network Failures**

```javascript
async function captureNetworkFailures(page) {
  const failures = [];

  page.on('requestfailed', request => {
    failures.push({
      url: request.url(),
      method: request.method(),
      error: request.failure()?.errorText,
    });
  });

  return failures;
}

test('No critical network failures', async ({ page }) => {
  const failures = await captureNetworkFailures(page);

  await page.goto('/test-no-elem/');
  await page.waitForLoadState('networkidle');

  // Filter critical failures (ignore analytics)
  const critical = failures.filter(f =>
    !f.url.includes('analytics.google.com') &&
    !f.url.includes('facebook.com/pixel')
  );

  expect(critical).toHaveLength(0);
});
```

---

## 📊 Real-World Examples

### **Daily Smoke Test**

```bash
#!/bin/bash
# Run every day at 9am

npm run test:smoke

if [ $? -eq 0 ]; then
  echo "✅ Production health check: PASSED"
else
  echo "❌ Production health check: FAILED"
  # Send alert to Slack/Email
fi
```

---

### **Pre-Deploy Checklist**

```bash
#!/bin/bash
# Run before every deployment

echo "🔍 Step 1: Check sync status"
npm run sync

echo "🧪 Step 2: Run local smoke tests"
npm run test:smoke

if [ $? -eq 0 ]; then
  echo "✅ Tests passed - ready to deploy"
  read -p "Deploy to production? (y/n) " -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    npm run deploy:test
  fi
else
  echo "❌ Tests failed - fix before deploying"
  exit 1
fi
```

---

### **Multi-Platform Test Report**

```bash
#!/bin/bash
# Test on all platforms and generate report

echo "Testing on Chrome Desktop..."
npx playwright test --project=chrome-desktop --reporter=html

echo "Testing on Firefox Desktop..."
npx playwright test --project=firefox-desktop --reporter=html

echo "Testing on Mobile..."
npm run test:mobile --reporter=html

echo "✅ All tests complete"
npx playwright show-report
```

---

## 🔗 Integration with CI/CD

### **GitHub Actions Example**

```yaml
name: E2E Tests

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: 18

      - name: Install dependencies
        run: npm install

      - name: Install Playwright
        run: npx playwright install --with-deps

      - name: Run smoke tests
        run: npm run test:smoke

      - name: Upload report
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: playwright-report/
```

---

**Last Updated:** 2026-02-17
