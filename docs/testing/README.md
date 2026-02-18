# Testing Infrastructure

**Complete autonomous E2E testing setup for Supabase Bridge plugin**

---

## 🎯 Overview

This testing infrastructure provides:
- ✅ **Autonomous deployment** - Deploy to production without manual steps
- ✅ **Automatic testing** - Test all user journeys after deployment
- ✅ **Automatic rollback** - Revert if tests fail
- ✅ **Multi-platform testing** - Chrome, Firefox, Safari, iPhone, Android, iPad
- ✅ **Special scenarios** - Slow connection, VPN fallback, error handling

---

## 📋 Prerequisites

```bash
# Playwright should already be installed
npm install

# Ensure Playwright browsers are installed
npx playwright install
```

---

## 🚀 Quick Start

### **1. Check Sync Status**

Before deploying, check if local files match production:

```bash
npm run sync

# Or manually:
./scripts/sync-production.sh
```

**Output:**
```
✅ auth-form.html - SYNCED
⚠️  callback.html - OUT OF SYNC
```

### **2. Deploy to Production**

```bash
# Deploy specific files
npm run deploy auth-form.html callback.html

# Or deploy all plugin files
npm run deploy
```

### **3. Run Tests**

```bash
# Quick smoke test (30 seconds)
npm run test:smoke

# Full E2E tests
npm run test:production

# Test specific platforms
npm run test:chrome    # Chrome Desktop
npm run test:mobile    # iPhone & Android
npm run test:special   # Slow connection, VPN fallback

# All tests
npm run test:all
```

### **4. Deploy AND Test (Recommended)**

Full pipeline with automatic rollback if tests fail:

```bash
npm run deploy:test

# Or manually:
./scripts/deploy-and-test.sh
```

**What happens:**
1. ✅ Creates production backup
2. ✅ Deploys files
3. ✅ Runs smoke tests
4. ✅ If tests pass → Done!
5. ❌ If tests fail → Automatic rollback

---

## 📁 Scripts Overview

### **Deployment Scripts**

| Script | Purpose |
|--------|---------|
| `scripts/deploy.sh` | Deploy files to production |
| `scripts/sync-production.sh` | Check local vs production sync |
| `scripts/create-backup.sh` | Create timestamped backup |
| `scripts/rollback.sh` | Restore from last backup |
| `scripts/deploy-and-test.sh` | Full pipeline (deploy → test → rollback if fail) |

### **Test Scripts**

| Command | Tests |
|---------|-------|
| `npm run test:smoke` | Quick health check (8 tests, ~30s) |
| `npm run test:chrome` | Chrome Desktop E2E |
| `npm run test:mobile` | iPhone, Android, iPad |
| `npm run test:special` | Slow connection, VPN, errors |
| `npm run test:all` | All E2E tests |
| `npm run test:production` | Full suite with HTML report |

---

## 🧪 Test Coverage

### **Smoke Tests** (`smoke.spec.js`)

Quick production health check:
- ✅ Auth page loads
- ✅ Google OAuth button visible
- ✅ Facebook OAuth button visible
- ✅ Email input visible
- ✅ OTP code toggle visible
- ✅ Callback page works
- ✅ WordPress REST API accessible
- ✅ Plugin REST API responds

### **Chrome Desktop** (`chrome-desktop.spec.js`)

Full user journey on Chrome:
- ✅ Google OAuth redirect
- ✅ Facebook OAuth redirect
- ✅ Magic Link email submission
- ✅ OTP code button visible
- ✅ Callback hash handling
- ✅ No critical JavaScript errors
- ✅ Supabase client initializes

### **Mobile** (`mobile.spec.js`)

Mobile-specific testing:
- ✅ iPhone 14 Pro (390x844)
- ✅ Samsung Galaxy S21 (360x800)
- ✅ iPad Pro (1024x1366)
- ✅ Touch events
- ✅ Responsive layout

### **Special Scenarios** (`special-scenarios.spec.js`)

Edge cases and error handling:
- ✅ Slow 3G connection
- ✅ VPN/OTP fallback
- ✅ Missing tokens
- ✅ Malformed tokens
- ✅ Network timeouts
- ✅ Rapid multiple clicks
- ✅ localStorage availability

---

## 🔍 Diagnostic Features

### **Console Error Tracking**

All tests capture:
- 🔴 JavaScript errors (ReferenceError, TypeError, SyntaxError)
- ⚠️ Console warnings
- ❌ Network failures (404, 401, 500, timeouts)

### **Screenshots & Videos**

Automatic on failure:
- 📸 Screenshot at point of failure
- 🎬 Video recording of entire test

Location: `test-results/`

### **HTML Report**

After running tests:

```bash
npx playwright show-report
```

View:
- ✅ Passed/failed tests
- 📊 Test duration
- 📸 Screenshots
- 🎬 Videos
- 📝 Error traces

---

## 🎭 Playwright Projects

Test across multiple browsers and devices:

```javascript
// playwright.config.js
projects: [
  'chrome-desktop',        // Desktop Chrome
  'firefox-desktop',       // Desktop Firefox
  'safari-desktop',        // Desktop Safari
  'iphone-14-pro',         // iPhone 14 Pro
  'samsung-galaxy-s21',    // Samsung Galaxy S21
  'ipad-pro',              // iPad Pro
  'slow-3g',               // Slow connection
]
```

Run specific project:

```bash
npx playwright test --project=iphone-14-pro
```

---

## 🔄 Typical Workflow

### **Scenario 1: Fix a Bug**

```bash
# 1. Make code changes
vim auth-form.html

# 2. Check what needs deployment
npm run sync

# 3. Deploy and test automatically
npm run deploy:test

# If tests pass → Done!
# If tests fail → Automatic rollback
```

### **Scenario 2: Add New Feature**

```bash
# 1. Develop feature
# ...

# 2. Write tests
vim tests/e2e-production/my-feature.spec.js

# 3. Test locally
npm run test:smoke

# 4. Deploy to production
npm run deploy:test
```

### **Scenario 3: Manual Rollback**

```bash
# If something went wrong
./scripts/rollback.sh
```

---

## 📊 Test Output Example

```bash
$ npm run deploy:test

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 Deploy and Test Pipeline
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📦 Step 1/4: Creating production backup...
✅ Backup created: backup_20260217_223000

📤 Step 2/4: Deploying to production...
✅ Deployed: auth-form.html
✅ Deployed: callback.html

🧪 Step 3/4: Running production smoke tests...
✅ Auth page loads successfully
✅ Google OAuth button is visible
✅ Facebook OAuth button is visible
✅ Email input and submit button are visible
✅ OTP code toggle button is visible
✅ Callback page loads successfully
✅ WordPress REST API is accessible
✅ Supabase Bridge REST API endpoint exists

  8 passed (12.3s)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Deploy and Test: SUCCESS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎉 All smoke tests passed!
🎉 Production deployment successful!
```

---

## 🚨 Troubleshooting

### **Tests failing locally but passing in production?**

```bash
# Run tests with --headed to see what's happening
npm run test:headed
```

### **Deploy fails with SSH error?**

```bash
# Check SSH key exists
ls -lh ~/.ssh/claude_prod_new

# Check .production-credentials
cat .production-credentials
```

### **Rollback not working?**

```bash
# Check if backup exists
ssh -i ~/.ssh/claude_prod_new -p YOUR_SSH_PORT YOUR_SSH_USER@YOUR_SERVER_IP \
  "ls -lh YOUR_WP_PATH/wp-content/plugins/supabase-bridge/backups/"
```

---

## 🎯 Best Practices

1. **Always run `npm run sync` before deploying** - Check what's out of sync
2. **Use `npm run deploy:test` instead of manual deploy** - Automatic testing + rollback
3. **Run smoke tests after every deployment** - Quick health check
4. **Run full E2E tests weekly** - Catch regressions
5. **Check HTML report after failures** - Screenshots show exactly what went wrong

---

## 📚 Related Documentation

- [Playwright Docs](https://playwright.dev/)
- [Test Best Practices](https://playwright.dev/docs/best-practices)
- [CI/CD Integration](https://playwright.dev/docs/ci)

---

**Last Updated:** 2026-02-17
