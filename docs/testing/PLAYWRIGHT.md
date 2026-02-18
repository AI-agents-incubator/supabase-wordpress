# Playwright Setup & Configuration

**Comprehensive guide to Playwright testing infrastructure**

---

## 📋 Overview

Playwright is used for autonomous end-to-end testing of the authentication flow on production.

**Key capabilities:**
- ✅ Test on multiple browsers (Chrome, Firefox, Safari)
- ✅ Test on multiple devices (iPhone, Android, iPad)
- ✅ Capture screenshots and videos on failure
- ✅ Monitor console errors and network failures
- ✅ Emulate slow connections and special scenarios

---

## 🚀 Installation

```bash
# Install Playwright
npm install

# Install browsers
npx playwright install

# Install specific browser only
npx playwright install chromium
```

---

## ⚙️ Configuration

### **File:** `playwright.config.js`

```javascript
export default defineConfig({
  testDir: './tests',
  baseURL: 'https://alexeykrol.com',

  use: {
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    // 7 different configurations
  ],
});
```

### **Base URL**

```javascript
baseURL: 'https://alexeykrol.com'
```

All tests use this as base. Navigate with relative paths:

```javascript
await page.goto('/test-no-elem/');
// Resolves to: https://alexeykrol.com/test-no-elem/
```

---

## 🎭 Projects (Browser & Device Configurations)

### **1. Chrome Desktop**

```javascript
{
  name: 'chrome-desktop',
  use: { ...devices['Desktop Chrome'] }
}
```

**Viewport:** 1280x720
**User Agent:** Chrome/145.x

### **2. Firefox Desktop**

```javascript
{
  name: 'firefox-desktop',
  use: { ...devices['Desktop Firefox'] }
}
```

**Viewport:** 1280x720
**User Agent:** Firefox/134.x

### **3. Safari Desktop**

```javascript
{
  name: 'safari-desktop',
  use: { ...devices['Desktop Safari'] }
}
```

**Viewport:** 1280x720
**User Agent:** Safari/18.x (WebKit)

### **4. iPhone 14 Pro**

```javascript
{
  name: 'iphone-14-pro',
  use: { ...devices['iPhone 14 Pro'] }
}
```

**Viewport:** 390x844
**User Agent:** Mobile Safari iOS 16
**Touch:** Enabled

### **5. Samsung Galaxy S21**

```javascript
{
  name: 'samsung-galaxy-s21',
  use: { ...devices['Galaxy S21'] }
}
```

**Viewport:** 360x800
**User Agent:** Chrome Mobile Android 12
**Touch:** Enabled

### **6. iPad Pro**

```javascript
{
  name: 'ipad-pro',
  use: { ...devices['iPad Pro'] }
}
```

**Viewport:** 1024x1366
**User Agent:** Mobile Safari iPadOS 16
**Touch:** Enabled

### **7. Slow 3G**

```javascript
{
  name: 'slow-3g',
  use: {
    ...devices['Desktop Chrome'],
    // Custom network throttling
  }
}
```

**Purpose:** Test on slow internet connection

---

## 📂 Test Structure

```
tests/
├── auth-diagnostic.spec.js           # Initial diagnostic test
└── e2e-production/
    ├── smoke.spec.js                 # Quick health check (8 tests)
    ├── chrome-desktop.spec.js        # Chrome E2E (8 tests)
    ├── mobile.spec.js                # Mobile devices (6 tests)
    └── special-scenarios.spec.js     # Edge cases (8 tests)
```

---

## 🧪 Test Types

### **Smoke Tests** (`smoke.spec.js`)

**Purpose:** Quick production health check
**Duration:** ~30 seconds
**Tests:** 8

```javascript
test('Auth page loads successfully', async ({ page }) => {
  await page.goto('/test-no-elem/');
  await expect(page).toHaveTitle(/Вход.*Регистрация/);
});
```

**What it checks:**
- ✅ Page loads without errors
- ✅ OAuth buttons visible
- ✅ Email input visible
- ✅ OTP code toggle visible
- ✅ Callback page works
- ✅ REST API responds

### **E2E Tests** (`chrome-desktop.spec.js`, `mobile.spec.js`)

**Purpose:** Full user journey testing
**Duration:** ~2-3 minutes
**Tests:** 14

```javascript
test('Google OAuth button triggers redirect', async ({ page }) => {
  await page.goto('/test-no-elem/');
  await page.locator('#sb-google-btn').click();

  await page.waitForURL(/accounts\.google\.com/);
  expect(page.url()).toContain('client_id');
});
```

**What it tests:**
- ✅ Google OAuth redirect
- ✅ Facebook OAuth redirect
- ✅ Magic Link submission
- ✅ OTP code button visibility
- ✅ Callback hash handling
- ✅ No JavaScript errors
- ✅ Supabase client initialization

### **Special Scenarios** (`special-scenarios.spec.js`)

**Purpose:** Edge cases and error handling
**Duration:** ~1-2 minutes
**Tests:** 8

```javascript
test('Auth page loads on Slow 3G', async ({ page, context }) => {
  await context.route('**/*', async (route) => {
    await new Promise(resolve => setTimeout(resolve, 500));
    await route.continue();
  });

  await page.goto('/test-no-elem/', { timeout: 30000 });
  await expect(page.locator('#sb-google-btn')).toBeVisible();
});
```

**What it tests:**
- ✅ Slow connection (Slow 3G simulation)
- ✅ VPN/OTP fallback button
- ✅ Missing tokens handling
- ✅ Malformed tokens handling
- ✅ Network timeouts
- ✅ Rapid multiple clicks
- ✅ localStorage availability

---

## 🎬 Running Tests

### **Run All Tests**

```bash
npx playwright test
```

### **Run Specific File**

```bash
npx playwright test tests/e2e-production/smoke.spec.js
```

### **Run Specific Project**

```bash
npx playwright test --project=iphone-14-pro
```

### **Run with Visible Browser**

```bash
npx playwright test --headed
```

### **Run in Debug Mode**

```bash
npx playwright test --debug
```

### **Run with Reporter**

```bash
# Line reporter (compact)
npx playwright test --reporter=line

# HTML reporter (detailed)
npx playwright test --reporter=html
```

---

## 📊 Reports

### **HTML Report**

After running tests:

```bash
npx playwright show-report
```

Opens interactive HTML report with:
- ✅ Test results (passed/failed)
- ⏱️ Duration
- 📸 Screenshots (on failure)
- 🎬 Videos (on failure)
- 📝 Error traces
- 🌐 Network logs

### **Line Reporter**

Real-time output during test run:

```
[1/8] smoke.spec.js:14 › Auth page loads successfully ✅
[2/8] smoke.spec.js:37 › Google OAuth button visible ✅
[3/8] smoke.spec.js:45 › Facebook OAuth button visible ✅
...
8 passed (12.3s)
```

---

## 🔍 Diagnostics

### **Console Error Tracking**

All tests capture console errors:

```javascript
let consoleErrors = [];

test.beforeEach(async ({ page }) => {
  page.on('console', msg => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
    }
  });
});
```

### **Network Failure Tracking**

All tests capture failed network requests:

```javascript
let networkFailures = [];

test.beforeEach(async ({ page }) => {
  page.on('requestfailed', request => {
    networkFailures.push({
      url: request.url(),
      method: request.method(),
      failure: request.failure()?.errorText,
    });
  });
});
```

### **Screenshots**

Automatic on failure:

```javascript
use: {
  screenshot: 'only-on-failure',
}
```

Location: `test-results/[test-name]/test-failed-1.png`

### **Videos**

Automatic on failure:

```javascript
use: {
  video: 'retain-on-failure',
}
```

Location: `test-results/[test-name]/video.webm`

---

## 🎯 Best Practices

### **1. Use Data-Testid for Stable Selectors**

```javascript
// ❌ Bad - brittle
await page.locator('.btn-primary').click();

// ✅ Good - stable
await page.locator('#sb-google-btn').click();
```

### **2. Wait for Network Idle**

```javascript
await page.goto('/test-no-elem/');
await page.waitForLoadState('networkidle');
```

### **3. Use Expect Assertions**

```javascript
// ❌ Bad
const isVisible = await button.isVisible();
if (!isVisible) throw new Error('Not visible');

// ✅ Good
await expect(button).toBeVisible();
```

### **4. Handle Timeouts**

```javascript
// Increase timeout for slow operations
await expect(button).toBeVisible({ timeout: 10000 });
```

### **5. Cleanup Test Data**

```javascript
test.afterEach(async () => {
  // Clear localStorage
  await page.evaluate(() => localStorage.clear());
});
```

---

## 🐛 Debugging

### **Run in Headed Mode**

```bash
npx playwright test --headed
```

See browser window during test execution.

### **Run in Debug Mode**

```bash
npx playwright test --debug
```

Opens Playwright Inspector:
- ⏯️ Step through test
- 🔍 Inspect page state
- 📝 View console logs
- 🌐 View network requests

### **Use console.log**

```javascript
test('Debug test', async ({ page }) => {
  await page.goto('/test-no-elem/');

  const title = await page.title();
  console.log('Page title:', title);

  const url = page.url();
  console.log('Current URL:', url);
});
```

### **Take Manual Screenshot**

```javascript
await page.screenshot({ path: 'debug.png' });
```

### **Check Element Visibility**

```javascript
const isVisible = await page.locator('#sb-google-btn').isVisible();
console.log('Button visible:', isVisible);

const count = await page.locator('#sb-google-btn').count();
console.log('Button count:', count);
```

---

## 📚 Useful Commands

```bash
# Install browsers
npx playwright install

# Update Playwright
npm install -D @playwright/test@latest
npx playwright install

# Generate tests with Codegen
npx playwright codegen https://alexeykrol.com/test-no-elem/

# Show HTML report
npx playwright show-report

# Show trace viewer
npx playwright show-trace test-results/trace.zip

# List available devices
npx playwright show-devices
```

---

## 🔗 Resources

- [Playwright Docs](https://playwright.dev/)
- [API Reference](https://playwright.dev/docs/api/class-playwright)
- [Best Practices](https://playwright.dev/docs/best-practices)
- [CI/CD Integration](https://playwright.dev/docs/ci)

---

**Last Updated:** 2026-02-17
