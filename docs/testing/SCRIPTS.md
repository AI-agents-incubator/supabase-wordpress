# Deploy & Testing Scripts

**Detailed documentation for all deployment and testing scripts**

---

## 📋 Overview

All scripts are located in `scripts/` directory and are designed for autonomous deployment and testing workflow.

---

## 🚀 Deployment Scripts

### **1. `deploy.sh`**

**Purpose:** Deploy files to production server

**Usage:**
```bash
# Deploy specific files
./scripts/deploy.sh auth-form.html callback.html

# Deploy all plugin files
./scripts/deploy.sh
```

**What it does:**
1. Reads `.production-credentials` for SSH settings
2. Validates SSH key exists (`~/.ssh/claude_prod_new`)
3. Uploads files via SCP to production
4. Shows success/failure for each file
5. Displays summary

**Output:**
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 Deploying to Production
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📤 Uploading: auth-form.html
✅ Deployed: auth-form.html

📤 Uploading: callback.html
✅ Deployed: callback.html

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Deploy Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Deployed: 2
❌ Failed:   0

🎉 Deploy completed successfully!
```

**Exit codes:**
- `0` - Success (all files deployed)
- `1` - Failure (one or more files failed)

---

### **2. `sync-production.sh`**

**Purpose:** Check if local files match production

**Usage:**
```bash
./scripts/sync-production.sh

# Or via npm:
npm run sync
```

**What it does:**
1. Computes MD5 hash of local files
2. Computes MD5 hash of production files (via SSH)
3. Compares hashes
4. Reports sync status for each file

**Output:**
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔍 Checking Production Sync Status
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ supabase-bridge.php - SYNCED
⚠️  auth-form.html - OUT OF SYNC
   Local:  a1b2c3d4e5f6...
   Remote: 9f8e7d6c5b4a...
✅ callback.html - SYNCED

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Sync Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total files:      3
✅ Synced:        2
⚠️  Out of sync:  1

Files needing deployment:
  - auth-form.html

💡 To deploy these files:
   ./scripts/deploy.sh auth-form.html
```

**Exit codes:**
- `0` - All files synced
- `1` - Files out of sync

---

### **3. `create-backup.sh`**

**Purpose:** Create timestamped backup on production server

**Usage:**
```bash
./scripts/create-backup.sh
```

**What it does:**
1. Creates backup directory on production: `backups/backup_YYYYMMDD_HHMMSS/`
2. Copies current production files to backup
3. Saves timestamp for rollback reference
4. Stores backup name in `.last-backup` locally

**Output:**
```
📦 Creating production backup: backup_20260217_223000
Backed up: supabase-bridge.php
Backed up: auth-form.html
Backed up: callback.html
Backup created: backup_20260217_223000
✅ Backup created successfully: backup_20260217_223000
```

**Files backed up:**
- `supabase-bridge.php`
- `auth-form.html`
- `callback.html`

**Backup location (production):**
```
YOUR_WP_PATH/
  wp-content/plugins/supabase-bridge/backups/
    backup_20260217_223000/
      supabase-bridge.php
      auth-form.html
      callback.html
      .timestamp
```

---

### **4. `rollback.sh`**

**Purpose:** Restore files from last backup

**Usage:**
```bash
./scripts/rollback.sh
```

**What it does:**
1. Reads `.last-backup` to get backup name
2. Connects to production via SSH
3. Restores files from backup directory
4. Reports success/failure

**Output:**
```
🔙 Rolling back to: backup_20260217_223000
Restored: supabase-bridge.php
Restored: auth-form.html
Restored: callback.html
Rollback completed: backup_20260217_223000
✅ Rollback successful
```

**Requirements:**
- `.last-backup` file must exist (created by `create-backup.sh`)
- Backup directory must exist on production

**Exit codes:**
- `0` - Rollback successful
- `1` - Backup not found or rollback failed

---

### **5. `deploy-and-test.sh`**

**Purpose:** Full deployment pipeline with automatic testing and rollback

**Usage:**
```bash
./scripts/deploy-and-test.sh auth-form.html callback.html

# Or via npm:
npm run deploy:test
```

**What it does:**
1. ✅ **Step 1:** Create production backup
2. ✅ **Step 2:** Deploy files to production
3. ✅ **Step 3:** Run smoke tests on production
4. ✅ **Step 4 (if tests pass):** Success! Done.
5. ❌ **Step 4 (if tests fail):** Automatic rollback

**Output (Success):**
```
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

**Output (Failure + Rollback):**
```
🧪 Step 3/4: Running production smoke tests...
❌ Auth page loads successfully - FAILED
  Error: Page returned 500

  1 failed, 7 passed (8.2s)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
❌ Deploy and Test: FAILED
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚠️  Smoke tests failed!

🔙 Step 4/4: Rolling back...
Restored: auth-form.html
Restored: callback.html
✅ Rollback successful
⚠️  Production restored to previous state
```

**Exit codes:**
- `0` - Deploy and tests successful
- `1` - Tests failed, rollback successful
- `2` - Tests failed, rollback also failed (manual intervention needed)

**Safety features:**
- ✅ Always creates backup before deploying
- ✅ Runs smoke tests to verify deployment
- ✅ Automatic rollback if tests fail
- ✅ Production never left in broken state

---

## 🧪 Test Scripts (via npm)

### **`npm run test:smoke`**

**Purpose:** Quick production health check

**Runs:** `tests/e2e-production/smoke.spec.js` (8 tests, ~30 seconds)

**Usage:**
```bash
npm run test:smoke
```

---

### **`npm run test:chrome`**

**Purpose:** Full E2E tests on Chrome Desktop

**Runs:** `tests/e2e-production/chrome-desktop.spec.js`

**Usage:**
```bash
npm run test:chrome
```

---

### **`npm run test:mobile`**

**Purpose:** Mobile device testing (iPhone, Android, iPad)

**Runs:** `tests/e2e-production/mobile.spec.js`

**Usage:**
```bash
npm run test:mobile
```

---

### **`npm run test:special`**

**Purpose:** Special scenarios (slow connection, VPN, errors)

**Runs:** `tests/e2e-production/special-scenarios.spec.js`

**Usage:**
```bash
npm run test:special
```

---

### **`npm run test:all`**

**Purpose:** All E2E tests on all platforms

**Runs:** All tests in `tests/e2e-production/`

**Usage:**
```bash
npm run test:all
```

---

### **`npm run test:production`**

**Purpose:** Full test suite with HTML report

**Runs:** All tests with HTML reporter

**Usage:**
```bash
npm run test:production

# View report
npx playwright show-report
```

---

### **`npm run test:headed`**

**Purpose:** Run tests with visible browser (for debugging)

**Runs:** All tests with browser window visible

**Usage:**
```bash
npm run test:headed
```

---

## 🔒 Security & Credentials

### **SSH Key**

Location: `~/.ssh/claude_prod_new`

Used by all deployment scripts for SSH/SCP authentication.

### **Production Credentials**

File: `.production-credentials`

Contains:
- SSH_HOST
- SSH_USER
- SSH_PORT
- WP_PATH
- Supabase credentials
- Claude API key

**⚠️ This file is in `.gitignore` and NEVER committed to git!**

---

## 📊 Dependencies

All scripts depend on:

1. `.production-credentials` file exists
2. SSH key exists at `~/.ssh/claude_prod_new`
3. SSH key has correct permissions (`chmod 600`)
4. Production server is accessible
5. For testing scripts: Playwright installed (`npm install`)

---

## 🛠️ Troubleshooting

### **"SSH key not found"**

```bash
# Check key exists
ls -lh ~/.ssh/claude_prod_new

# If not found, restore from backup or regenerate
```

### **"Permission denied (publickey)"**

```bash
# Check key permissions
chmod 600 ~/.ssh/claude_prod_new

# Verify public key is added to production server
ssh -i ~/.ssh/claude_prod_new -p YOUR_SSH_PORT YOUR_SSH_USER@YOUR_SERVER_IP
```

### **"Backup not found"**

```bash
# Check if .last-backup exists
cat .last-backup

# List backups on production
ssh -i ~/.ssh/claude_prod_new -p YOUR_SSH_PORT YOUR_SSH_USER@YOUR_SERVER_IP \
  "ls -lh YOUR_WP_PATH/wp-content/plugins/supabase-bridge/backups/"
```

---

**Last Updated:** 2026-02-17
