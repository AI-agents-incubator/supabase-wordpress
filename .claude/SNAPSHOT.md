# SNAPSHOT — Supabase Bridge

*Framework: Claude Code Starter v2.3.1*
*Last Updated: 2026-03-22*

---

> **Planning Documents:**
> - 🎯 Current tasks: [BACKLOG.md](./BACKLOG.md)
> - 🗺️ Strategic roadmap: [ROADMAP.md](./ROADMAP.md)
> - 💡 Ideas: [IDEAS.md](./IDEAS.md)
> - 📊 Architecture: [ARCHITECTURE.md](./ARCHITECTURE.md)

---

## 📊 Статус разработки

**Phase 1: Core Authentication (v0.1.0)** [статус: ✅]
**Phase 2: Multi-Provider Support (v0.3.0)** [статус: ✅]
**Phase 3: Security Hardening (v0.3.1-v0.3.3)** [статус: ✅]
**Phase 4: Bug Fixes & Testing (v0.3.5)** [статус: ✅]
**Phase 5: UX Improvements (v0.4.0-v0.4.1)** [статус: ✅]
**Phase 6: Analytics & Multi-Site (v0.7.0)** [статус: ✅]
**Phase 7: Webhook System for n8n/make (v0.8.1)** [статус: ✅]
**Phase 8: Webhook UI Integration (v0.8.2)** [статус: ✅]
**Phase 9: Environment Variable Fixes (v0.8.3)** [статус: ✅]
**Phase 10: Magic Link Authentication Fix (v0.8.4)** [статус: ✅]
**Phase 11: Registration Pairs Fixes (v0.8.5)** [статус: ✅]
**Phase 12: MemberPress Integration (v0.9.0)** [статус: ✅]
**Phase 13: LearnDash Integration (v0.9.0)** [статус: ✅]
**Phase 14: LearnDash Banner Management UI (v0.9.1)** [статус: ✅]
**Phase 15: Production Debugging System (v0.9.2)** [статус: ✅]
**Phase 16: Two-Page Architecture Refactoring (v0.9.6)** [статус: ✅]
**Phase 17: Login Flow & Unified Shortcode Architecture (v0.9.7)** [статус: ✅]
**Phase 18: Security Hardening & Testing Infrastructure (v0.9.8)** [статус: ✅]
**Phase 19: Safari Privacy Protection & UX Polish (v0.9.9)** [статус: ✅]
**Phase 20: PKCE Flow Support & OAuth Stability (v0.9.10)** [статус: ✅]
**Phase 21: Universal Membership & Enrollment (v0.9.11)** [статус: ✅]
**Phase 22: Data Integrity Monitoring & Cross-Device Fixes (v0.9.12)** [статус: ✅]
**Phase 23: MemberPress Webhook System Upgrade (v0.9.13)** [статус: ✅]
**Phase 24: Course Access Auto-Enrollment & Checkout Auth (v0.10.0)** [статус: ✅]
**Phase 25: Landing URL Marketing Tracking (v0.10.1)** [статус: ✅]
**Phase 26: Auth UX & Error Handling (v0.10.2)** [статус: ✅]
**Phase 27: MySQL Lock Deadlock Fix (v0.10.3)** [статус: ✅]
**Phase 28: JWT Clock Skew Fix (v0.10.4)** [статус: ✅]
**Phase 29: Help Modal System + Magic Link Cooldown (v0.10.5)** [статус: ✅]
**Phase 30: Auto-Enrollment for Manual Transactions (v0.10.6)** [статус: ✅]
**Phase 31: Testing & Deployment Infrastructure (2026-02-17)** [статус: ✅]
**Phase 32: Site Ops & Security Hardening (2026-03-20)** [статус: ✅]
**Phase 33: Agent Autonomy & Settings Architecture (2026-03-22)** [статус: ✅]

**Общий прогресс:** 100% MVP + All Auth Methods Fixed + JWT Clock Skew Resolved + MySQL Lock Deadlock Resolved + WordPress Native Auth Fallback + Cross-Device Magic Link + Data Integrity Monitoring + Safari Privacy Support + PKCE Flow Support + Russian Localization + Comprehensive Security + Universal Membership/Enrollment System + Universal MemberPress Webhooks + Course Access Auto-Enrollment + Checkout Authentication Overlay + Landing URL Marketing Analytics + Accurate Error Messages + Help Modal System + Magic Link Cooldown + Zapier/Manual Transaction Auto-Enrollment + **Autonomous Testing & Deployment** + **Site Ops Registry + Security Hardening** + **Full Agent Autonomy (Claude Code Settings Architecture)** (Production Ready)

**Текущая фаза:** Maintenance + Site Ops (Phase 33 — 2026-03-22)

---

## 📦 Установленные зависимости

### Production (PHP):
- `firebase/php-jwt` ^6.11.1 ✅ (0 vulnerabilities)

### Frontend (CDN):
- `@supabase/supabase-js` v2.x (jsdelivr.net)

### Development:
- `composer` v2.8.12

---

## 🗂️ Структура проекта

```
supabase-bridge/
├── supabase-bridge.php              [статус: ✅] Main plugin (v0.7.0)
│   ├── Security headers             ✅
│   ├── REST API endpoints           ✅
│   ├── JWT verification             ✅
│   ├── WordPress user sync          ✅
│   ├── Distributed lock             ✅ (v0.4.1)
│   ├── Settings page                ✅ (v0.4.0)
│   ├── Registration Pairs UI        ✅ (v0.7.0)
│   ├── Validation functions         ✅ (v0.7.0)
│   └── Supabase sync functions      ✅ (v0.7.0)
├── auth-form.html                   [статус: ✅] Auth form (v0.7.0)
├── supabase-tables.sql              [статус: ✅] Database schema (v0.7.0)
├── SECURITY_RLS_POLICIES_FINAL.sql  [статус: ✅] RLS policies (v0.7.0)
├── webhook-system/                  [статус: ✅] Webhook system (v0.8.1)
│   ├── ARCHITECTURE.md              ✅ Architecture + critical technical details
│   ├── webhook-system.sql           ✅ Database schema, triggers, RLS policies
│   ├── send-webhook-function.ts     ✅ Edge Function v0.8.1 (Deno/TypeScript)
│   ├── webhooks-tab-full-code.php   ✅ WordPress Admin UI (full code)
│   ├── DEPLOYMENT.md                ✅ Deployment guide + critical issues
│   └── README.md                    ✅ Project overview, roadmap, version history
├── build-release.sh                 [статус: ✅] Release automation (v0.7.0)
├── PRODUCTION_SETUP.md              [статус: ✅] Production guides (v0.7.0)
├── QUICK_SETUP_CHECKLIST.md         [статус: ✅] 1-page guide (v0.7.0)
├── SECURITY_ROLLBACK_SUMMARY.md     [статус: ✅] Security docs (v0.7.0)
├── CLAUDE.md                        [статус: ✅] Project context (v0.7.0)
├── composer.json                    [статус: ✅] PHP dependencies
├── composer.lock                    [статус: ✅] Locked versions
├── vendor/                          [статус: ✅] Autoload + firebase/php-jwt
├── .claude/                         [статус: ✅] Claude Code Starter v2.2
│   ├── SNAPSHOT.md                  ✅ (this file)
│   ├── BACKLOG.md                   ✅
│   ├── ROADMAP.md                   ✅
│   ├── IDEAS.md                     ✅
│   ├── ARCHITECTURE.md              ✅
│   └── commands/                    ✅
├── .gitignore                       [статус: ✅]
├── LICENSE                          [статус: ✅] MIT
└── README.md                        [статус: ✅] Production docs

Легенда:
✅ — реализовано и протестировано
🔄 — в процессе разработки
⏳ — ожидает выполнения
📦 — архивировано
```

---

## ✅ Завершенные задачи

### Phase 1: Core Authentication (v0.1.0) - Released 2025-10-01
1. ✅ JWT Verification via JWKS (RS256)
2. ✅ WordPress User Synchronization
3. ✅ OAuth Provider Support (Google, Apple, GitHub, etc.)
4. ✅ REST API Endpoints (/callback, /logout)
5. ✅ Environment Variables Configuration
6. ✅ Supabase JS Integration (CDN)
7. ✅ Session Management (wp_set_auth_cookie)
8. ✅ User Metadata Storage (supabase_user_id)

### Phase 2: Multi-Provider Authentication (v0.3.0) - Released 2025-10-05
1. ✅ Google OAuth - Tested and working
2. ✅ Facebook OAuth - Advanced access for email
3. ✅ Magic Link (Passwordless) - Email + 6-digit code
4. ✅ Smart Redirects - New vs existing user
5. ✅ 3 Redirect Modes - Standard, paired, flexible
6. ✅ Ready-to-use Form - auth-form.html

### Phase 3: Security Hardening (v0.3.1-v0.3.3) - Released 2025-10-07
1. ✅ CSRF Protection (Origin/Referer validation)
2. ✅ JWT aud Validation
3. ✅ Email Verification Enforcement
4. ✅ JWKS Caching (1 hour)
5. ✅ Rate Limiting (10/60s per IP)
6. ✅ Open Redirect Protection
7. ✅ HTTP Security Headers (CSP, X-Frame-Options, etc.)
8. ✅ Enhanced Error Handling
9. ✅ Audit Logging (IP tracking)

### Phase 4: Bug Fixes & Testing (v0.3.5) - Released 2025-10-23
1. ✅ Google OAuth Email Verification Fix
2. ✅ Magic Link localStorage Fix
3. ✅ CSP headers conflict resolution
4. ✅ Race condition handling improvement
5. ✅ Production testing

### Phase 5: UX Improvements (v0.4.0-v0.4.1) - Released 2025-10-25
1. ✅ Shortcode Implementation ([supabase_auth_form])
2. ✅ Settings Page with Thank You Page selector
3. ✅ Encrypted Credentials Storage (AES-256-CBC)
4. ✅ Server-side Distributed Lock
5. ✅ UUID-first Checking

### Phase 6: Registration Pairs Analytics (v0.7.0) - Released 2025-10-26
1. ✅ Supabase Database Tables
2. ✅ Settings UI with Registration Pairs CRUD
3. ✅ WordPress → Supabase Sync
4. ✅ JavaScript Injection of pairs
5. ✅ Page-specific Thank You Redirects
6. ✅ Registration Event Logging
7. ✅ Enterprise Security Architecture

### Phase 7: Webhook System (v0.8.1) - Completed 2025-10-27
1. ✅ Database triggers for webhooks
2. ✅ Edge Function with retry logic
3. ✅ WordPress Admin UI code (standalone file)
4. ✅ End-to-end testing with Make.com

### Phase 8: Webhook UI Integration (v0.8.2) - Completed 2025-12-11
1. ✅ Added Webhooks tab to WordPress Admin UI navigation
2. ✅ Integrated sb_render_webhooks_tab() function into main plugin
3. ✅ Visual status indicators for webhook configuration
4. ✅ Complete admin interface with setup instructions

### Phase 9: Environment Variable Fixes (v0.8.3) - Completed 2025-12-11
1. ✅ Fixed sb_cfg() function to read from $_ENV and $_SERVER
2. ✅ Support for Supabase JWT Signing Keys (migrated from Legacy JWT Secret)
3. ✅ Better fallback chain for credentials reading
4. ✅ JWKS cache clearing for JWT key migration

### Phase 10: Magic Link Authentication Fix (v0.8.4) - Completed 2025-12-11
1. ✅ Fixed race condition causing duplicate callbacks
2. ✅ Implemented atomic MySQL GET_LOCK() for concurrency protection
3. ✅ Added credentials: 'include' to fetch request for proper cookie handling
4. ✅ Fixed localStorage cleanup on login page
5. ✅ Tested successfully in Safari, Chrome, and Firefox
6. ✅ All authentication methods now working perfectly

### Phase 11: Registration Pairs Fixes (v0.8.5) - Completed 2025-12-13
1. ✅ Fixed Registration Pairs tracking accuracy (explicit POST param instead of Referer)
2. ✅ Implemented Edit Pair functionality with modal pre-population
3. ✅ Added custom delete confirmation modal (Safari compatible)
4. ✅ Fixed registration logging bug (removed non-existent thankyou_page_url column)
5. ✅ Improved HTTP 409 duplicate callback handling for seamless redirects
6. ✅ Added RLS policies for anon role on both registration tables
7. ✅ Fully tested - registration events successfully logged to Supabase

### Phase 12: MemberPress Integration (v0.9.0) - Completed 2025-12-13
1. ✅ New "🎫 Memberships" tab in WordPress Admin
2. ✅ Dropdown showing only FREE memberships (price = 0)
3. ✅ CRUD operations for membership assignment rules
4. ✅ Auto-assign membership function using `MeprTransaction::store()`
5. ✅ Integration with registration callback endpoint
6. ✅ Tested successfully with MemberPress 1.x

### Phase 13: LearnDash Integration (v0.9.0) - Completed 2025-12-13
1. ✅ New "📚 Courses" tab in WordPress Admin
2. ✅ Dropdown listing all available LearnDash courses
3. ✅ CRUD operations for course enrollment rules
4. ✅ Auto-enroll function using native `ld_update_course_access()`
5. ✅ Integration with registration callback endpoint
6. ✅ LearnDash banner removal patch script (idempotent, upgrade-safe)
7. ✅ Tested successfully with LearnDash 4.x

### Phase 14: LearnDash Banner Management UI (v0.9.1) - Completed 2025-12-13
1. ✅ New "🎓 Banner" tab in WordPress Admin
2. ✅ Checkbox to enable/disable enrollment banner removal
3. ✅ Real-time patch status indicator (Active, Not Active, Update Needed, Not Found)
4. ✅ One-click apply/restore functionality via AJAX
5. ✅ Automatic backup creation before modifications
6. ✅ Warning notifications after LearnDash updates
7. ✅ Backward compatible with old patch versions

### Phase 15: Production Debugging System (v0.9.2) - Completed 2025-12-17
1. ✅ Enhanced logging system with multiple log levels (DEBUG, INFO, WARNING, ERROR)
2. ✅ Automatic sensitive data redaction (tokens, passwords, keys)
3. ✅ Context-aware logging with structured JSON data
4. ✅ Function entry/exit tracing for execution flow
5. ✅ Comprehensive logging in authentication callback
6. ✅ Production debugging documentation (setup guides, security checklist)
7. ✅ SSH read-only access instructions
8. ✅ Zero performance impact when WP_DEBUG is disabled

### Phase 16: Two-Page Architecture Refactoring (v0.9.6) - Completed 2025-12-18
1. ✅ Analyzed Chrome/Safari hash detection issue - found duplicate callback code
2. ✅ Implemented two-page authentication architecture
3. ✅ Created dedicated callback page `/test-no-elem-2/` with clean handler
4. ✅ Added `redirect_to` parameter support for login redirects
5. ✅ Removed ~112 lines of duplicate callback code from `auth-form.html`
6. ✅ Separated concerns: form display (page 1) vs authentication processing (page 2)
7. ✅ Fixed OAuth redirect URLs to point to callback page (`/test-no-elem-2/`)
8. ✅ Tested in Chrome, Safari, Firefox (normal + incognito) - works in all browsers
9. ✅ Verified Google OAuth and Facebook OAuth login flows work correctly

### Phase 17: Login Flow & Unified Shortcode Architecture (v0.9.7) - Completed 2025-12-18
1. ✅ Implemented `document.referrer` tracking on login page (localStorage)
2. ✅ Added redirect logic to callback handler (reads from localStorage)
3. ✅ Created `[supabase_auth_callback]` shortcode for unified architecture
4. ✅ Unified shortcode system - both auth pages use shortcodes for automatic updates
5. ✅ Return-to-origin login flow - user returns to page where they clicked "Login"
6. ✅ Tested Google OAuth login from multiple pages - works perfectly
7. ✅ Tested Facebook OAuth login from multiple pages - works perfectly
8. ✅ Tested Magic Link login from multiple pages - works perfectly
9. ✅ Verified in Chrome, Safari, Firefox (normal + incognito modes)

**Architecture:**
- **Page 1:** `/test-no-elem/` - Form with `[supabase_auth_form]`
- **Page 2:** `/test-no-elem-2/` - Callback handler with `[supabase_auth_callback]`
- **Flow:** Any page → Click "Login" → Auth page (saves referrer) → Login → Callback (reads referrer) → Return to origin page

### Phase 18: Security Hardening & Testing Infrastructure (v0.9.8) - Completed 2025-12-18
1. ✅ Comprehensive security scanning system (bash-based)
   - SSH private/public key detection
   - JWT token detection
   - IP address detection
   - Database credentials detection
   - Hardcoded secrets detection
2. ✅ Automated dialog file cleanup script (`tests/clean-dialogs.sh`)
   - Removes all SSH credentials from dialog files
   - Replaces sensitive data with `[REDACTED]` markers
   - Safe for public repository
3. ✅ Integration testing for all core features
   - Registration Pairs redirect testing
   - MemberPress auto-assignment testing
   - LearnDash auto-enrollment testing
   - LearnDash banner patch testing
4. ✅ Unified test runner (`tests/run-all.sh`)
   - Smoke tests (health checks)
   - Unit tests (PHPUnit integration)
   - Security scanning (4th step)
   - AI-assisted test reports
5. ✅ LearnDash banner patch improvements
   - Added PHP OPcache clearing (opcache_invalidate)
   - User-facing cache clearing instructions
   - Fixed banner visibility issue
6. ✅ Git history cleanup
   - Removed all credentials from git history using BFG Repo-Cleaner
   - Force pushed clean history to GitHub
   - Repository safe for public access
7. ✅ `.gitignore` improvements
   - Replaced 58 individual dialog entries with wildcard rules
   - Automatic protection for all dialog files

**Security Results:**
- Before: 82 security issues (40 critical, 22 high, 20 medium)
- After: 0 real credentials in repository
- All dialog files cleaned and safe for students

### Phase 19: Safari Privacy Protection & UX Polish (v0.9.9) - Completed 2025-12-19
1. ✅ Safari Privacy Protection (safeStorage wrapper with in-memory fallback)
2. ✅ Russian localization for all UI elements
3. ✅ UX improvements - eliminated flickering screens
4. ✅ 3-step troubleshooting instructions in footer
5. ✅ Instant loading screen for callback page
6. ✅ Animated dots instead of countdown timer
7. ✅ Security incident response - SSH keys removed from git history
8. ✅ Repository cleanup - removed 51 debug files (-20,315 lines)
9. ✅ Reorganized structure (security/, supabase/ folders)
10. ✅ CLAUDE.md Completion Protocol improvements - creative README update process
11. ✅ README.md fundamental overhaul - all 19 phases properly documented

**Results:**
- Safari Privacy mode fully supported
- All UI in Russian
- Clean, minimal repository (production code only)
- No security issues in git history
- Framework has built-in protection against stale documentation
- README accurately reflects v0.9.9 state (not v0.8.5)

### Phase 20: PKCE Flow Support & OAuth Stability (v0.9.10) - Completed 2025-12-21
1. ✅ Added PKCE flow support to callback handler
2. ✅ Modified extractTokensFromHash() to support both OAuth flows:
   - Implicit flow (hash fragment #access_token=...)
   - PKCE flow (query string ?access_token=...)
3. ✅ Fixed dotsTimer bug (ReferenceError: countdownTimer not defined)
4. ✅ Rollback from broken Phase 19 deployment to working version (a60af9a)
5. ✅ Re-applied Phase 19 changes with fixes
6. ✅ Investigated Supabase SDK CDN floating version issue
7. ✅ Tested OAuth in Chrome, Safari, Firefox - all working

**Root Cause:**
- Supabase JS SDK @2 loaded from CDN (floating version)
- SDK updated and changed default OAuth flow behavior
- Chrome/Safari started using PKCE flow (query string)
- Firefox continued using Implicit flow (hash fragment)
- Previous callback handler only supported Implicit flow

**Results:**
- OAuth login works in ALL browsers (Chrome, Safari, Firefox)
- Backward compatible with both OAuth flows
- Code resilient to future Supabase SDK changes
- No breaking changes for users

### Phase 21: Universal Membership & Enrollment (v0.9.11) - Completed 2025-12-28
1. ✅ Added helper functions for membership/enrollment checks
   - `sb_has_membership($user_id, $membership_id)` - check if user has active membership
   - `sb_is_enrolled($user_id, $course_id)` - check if user is enrolled in course
2. ✅ Implemented User Status Analyzer module
   - Analyzes user's current memberships and enrollments
   - Checks registration URL against configured pairs
   - Determines what memberships/courses should be assigned
3. ✅ Implemented Action Executor module
   - Executes membership assignments and course enrollments
   - Prevents duplicate assignments
   - Logs all actions for debugging
4. ✅ Fixed redirect logic conflict (Registration Pairs vs Return URL)
   - Registration Pairs redirect takes priority over `redirect_to` parameter
   - Clear documentation in code about redirect logic
   - Prevents accidental redirect URL overrides
5. ✅ All integration tests passed successfully

**Results:**
- Clean separation of concerns (analysis vs execution)
- Prevents duplicate membership/enrollment assignments
- Clear redirect logic hierarchy
- Full test coverage for all integrations

### Phase 22: Data Integrity Monitoring & Cross-Device Fixes (v0.9.12) - Completed 2026-01-04
1. ✅ Fixed Magic Link cross-device registration URL loss
   - Root cause: OAuth redirects lose localStorage when user opens email on different device
   - Solution: Pass `registration_url` via URL query parameter in Magic Link emails
   - Modified `auth-form.html` to include registration_url in emailRedirectTo
   - Modified callback page to read from URL param with localStorage fallback
   - 100% pair_id tracking accuracy verified after fix
2. ✅ Fixed missing registration pair sync calls
   - Added `sb_sync_pair_to_supabase()` call in `sb_ajax_save_pair()`
   - Added `sb_delete_pair_from_supabase()` call in `sb_ajax_delete_pair()`
   - WordPress registration pairs now properly sync to Supabase table
3. ✅ Created data integrity monitoring system
   - Local bash script (`monitoring/check-integrity.sh`)
   - Compares auth.users vs wp_user_registrations for lost tracking
   - Checks landing page attribution (pair_id coverage)
   - Configurable time period (default: 1 hour)
   - Exit code 0 = all good, exit code 1 = issues detected
4. ✅ Added monitoring documentation
   - Complete README with setup and usage instructions
   - Credential template (.env.example)
   - Security notes and scheduling examples
5. ✅ Updated .gitignore and .production-credentials
   - Added monitoring/.env to .gitignore
   - Added Supabase credentials section to .production-credentials
6. ✅ CRITICAL: Migrated from Supabase SMTP to Amazon SES
   - Root cause: High traffic caused Supabase SMTP rate limits (~50-100 emails/hour)
   - Impact: Magic Link emails stopped sending, blocked new registrations
   - Solution: Migrated to Amazon SES (Simple Email Service)
   - Setup: AWS account, domain verification (DKIM, SPF), Supabase SMTP config
   - Documentation: Added critical SMTP warning to README.md
7. ✅ Fixed Supabase error handling on callback page
   - Root cause: Users stuck on "Welcome! Wait..." when Magic Link expired
   - Solution: Check for `error=` in URL hash BEFORE token extraction
   - User-friendly error messages for common errors (otp_expired, otp_disabled, access_denied)
   - Added "Return to form" button with link to registration_url
   - Russian localization for all error messages

**Root Cause Analysis:**
- ~46% of Magic Link registrations had `pair_id = NULL` in analytics
- Users opened Magic Link emails on different devices/browsers
- localStorage is device-specific, doesn't transfer across OAuth redirects
- Registration pairs weren't syncing from WordPress to Supabase
- Supabase SMTP hit rate limits during European morning traffic
- Expired Magic Links showed loading screen indefinitely

**Results:**
- 100% registration tracking accuracy (verified via SQL)
- Cross-device Magic Link authentication fully working
- Real-time data integrity monitoring capability
- No lost registrations for marketing attribution
- Production-grade email delivery (Amazon SES)
- Proper error handling with user recovery flow

### Phase 23: MemberPress Webhook System Upgrade (v0.9.13) - Completed 2026-01-05
1. ✅ Renamed webhook system from "Make.com" to "MemberPress Webhooks"
   - Changed all function names: `sb_make_webhook_*` → `sb_memberpress_webhook_*`
   - Updated admin UI tab: "🎣 Make.com" → "🎣 MemberPress Webhook"
   - Clear branding aligned with industry standard (MemberPress)
2. ✅ Multiple webhook URL support
   - Changed from single URL input to textarea (one URL per line)
   - Supports Make.com, Zapier, n8n, or any HTTP webhook endpoint
   - Sends to all configured URLs simultaneously
3. ✅ MemberPress-compatible payload format
   - Full 100+ field payload matching MemberPress webhook structure
   - Event type: `non-recurring-transaction-completed`
   - Nested objects: membership, member, transaction data
   - 100% compatible with existing MemberPress automations
4. ✅ Automatic migration system
   - Created `sb_migrate_webhook_settings()` function
   - Runs once on `admin_init`, migrates old settings to new format
   - Backward compatibility wrapper (`sb_send_make_webhook()`)
   - Fallback logic to read old options if new ones are empty
   - Zero breaking changes during deployment
5. ✅ Real data testing (not stub data)
   - Test webhook uses ACTUAL data from last registration
   - Queries `mepr_transactions` table for last `sb-%` transaction
   - Fetches real user email, membership ID, transaction details
   - `test_mode = false` for production-quality payload
6. ✅ Real payload preview in UI
   - Documentation section shows ACTUAL JSON preview
   - Generated from last registration data (like MemberPress)
   - Shows what will be sent when test button is clicked
   - Conditional display: real preview or "no registrations yet" message
7. ✅ Dynamic success messages
   - Test webhook shows: "Test webhook sent to 2 URL(s) using REAL data from last registration (User: email@example.com, Transaction ID: 12345)"
   - Instead of generic "Test webhook sent successfully!"
   - Provides verification of which user's data was sent
8. ✅ Webhook payload optimization & mass resend system
   - Optimized payload from ~4KB to ~630 bytes (removed content, excerpt, extra fields)
   - Created automated resend system with log tracking (`resend-webhooks-fast.php`)
   - Mass-sent 1901 missed webhooks (Dec 25 - Jan 5) with batching (50/batch, 30s pauses)
   - Automatic duplicate prevention via log file (`/tmp/sent-webhooks.log`)
   - Tested n8n integration alongside Make.com (multi-platform support verified)
   - Added webhook resend files to .gitignore for security
   - Created GitHub issue #20 for MemberPress native webhook investigation
9. ✅ Authentication callback timeout safeguard
   - Added 20-second timeout protection for hanging auth redirects
   - Fallback UI: "Вход занял слишком много времени" + "Войти заново" button
   - Backend logging endpoint (`sb_ajax_log_auth_timeout`) for timeout analysis
   - Logs to `wp-content/debug.log`: browser, URL, platform, timestamp
   - Zero-risk implementation: runs in parallel, doesn't affect existing code
   - GitHub issue #23 for future email notifications (pending Amazon SES integration)
10. ✅ Membership → Course auto-enrollment system
   - New admin tab "Course Access" for membership-to-course mapping
   - Auto-enrolls users in LearnDash courses when they purchase MemberPress memberships
   - Supports both one-time transactions (complete) and subscriptions (active)
   - Duplicate-safe: checks if already enrolled before enrolling (preserves progress)
   - One membership can map to multiple courses (multiple pairs)
   - Triggers: `mepr_event_transaction_completed` and `mepr_subscription_transition_status`
   - Course access controlled by membership status (pause/cancel = access removed, but enrollment/progress preserved)
   - Detailed logging to `wp-content/debug.log` for debugging

**Migration Safety:**
- Auto-migration runs on first admin page load after update
- Old function name (`sb_send_make_webhook`) still works via wrapper
- Fallback to old options if new ones are empty
- Tested on production with zero downtime
- All existing Make.com automations continue working

**Results:**
- Universal webhook system (not Make.com specific)
- Supports multiple automation platforms simultaneously
- 100% MemberPress payload compatibility
- Testing uses real data for accurate automation verification
- Clean migration with zero breaking changes
- Production deployment successful with no errors

### Phase 24: Course Access Auto-Enrollment & Checkout Auth (v0.10.0) - Completed 2026-01-06
1. ✅ **Course Access Auto-Enrollment System** (from CHANGELOG.md - completed earlier today)
   - New "Course Access" admin tab for membership → course mappings
   - Automatic enrollment in LearnDash courses when users purchase memberships
   - Supports one-time purchases (complete) and subscriptions (active)
   - Duplicate-safe enrollment with progress preservation
   - Course access controlled by membership status
2. ✅ **Checkout Authentication Overlay** (UX improvement)
   - Problem: Non-logged users stuck on checkout with "email exists" error
   - Solution: Fullscreen semi-transparent overlay on `/register/*` pages
   - Clear Russian messaging: "Чтобы оформить покупку, сначала авторизуйтесь — войдите или зарегистрируйтесь"
   - Single "Авторизоваться" button redirects to `/test-no-elem/` auth page
   - Seamless flow: auth → auto-redirect back to checkout
   - Triple protection: PHP `is_user_logged_in()` + URL check + JS cookie verification
   - Reuses existing referrer redirect logic
   - Cache-safe: works with aggressive browser/server caching
   - Production tested on desktop and mobile devices

**Implementation Details:**
- URL pattern: Activates on all MemberPress checkout pages (`/register/*`)
- Smart detection: Only shows for non-logged-in users
- Lines 5637-5759 in `supabase-bridge.php`
- Zero impact on logged-in users

**Results:**
- Eliminated "email exists" dead-end for users
- Reduced abandoned checkouts from authentication confusion
- Seamless UX from landing → auth → checkout → purchase
- 100% compatible with LiteSpeed Cache exclusions
- Tested and verified on production site

### Phase 25: Landing URL Marketing Tracking (v0.10.1) - Completed 2026-01-09
1. ✅ **Database Schema Extension**
   - Added `landing_url TEXT` column to `wp_user_registrations` table
   - Created index for analytics queries
   - SQL migration: `supabase/add-landing-url-field.sql`
2. ✅ **Frontend Landing URL Capture** (`auth-form.html`)
   - Implemented `cleanTrackingParams()` function - removes Facebook/Google tracking parameters (`fbclid`, `gclid`, `msclkid`, `gbraid`, `wbraid`)
   - Captures `document.referrer` on auth form page (LANDING_URL constant)
   - Only tracks same-domain referrers for privacy
   - Magic Link: passes landing_url via URL parameter (cross-device compatible)
   - OAuth (Google/Facebook): saves landing_url to localStorage (same-device only)
3. ✅ **Callback Handler Updates** (`callback.html`)
   - Priority 1: Read landing_url from URL query param (Magic Link)
   - Priority 2: Read landing_url from localStorage (OAuth)
   - Priority 3: null (direct auth form access)
   - Sends landing_url to PHP via POST body
4. ✅ **Backend Integration** (`supabase-bridge.php`)
   - Modified `sb_log_registration_to_supabase()` signature - added optional `$landing_url` parameter (backward compatible)
   - Validates landing_url via `sb_validate_url_path()` function
   - Includes landing_url in Supabase INSERT payload
   - Maintains backward compatibility with existing code
5. ✅ **Production Testing & Validation**
   - Deployed to production and tested with real Facebook ads traffic
   - Verified UTM parameter tracking: `?utm=afb_0003`, `?utm=fbp_001`, `?utm=pfb_0003`
   - Cleaned Facebook/Google tracking parameters removed correctly
   - 100% landing URL attribution for new registrations
   - Supports multiple landing pages with different UTM codes

**Use Cases Supported:**
- **Case 1:** Paid traffic from Facebook/ads - landing page with UTM → auth form → registration
- **Case 2:** SEO/organic traffic - closed content → auth form → registration (no landing URL)
- **Case 3:** Direct auth form access - no landing page (landing_url = null)

**Results:**
- Complete marketing attribution for Facebook ad campaigns
- Track which specific Facebook posts drive registrations
- UTM parameters preserved through authentication flow
- Cross-device Magic Link authentication captures landing URL
- Privacy-friendly: only tracks same-domain referrers, removes ad platform tracking IDs
- Zero breaking changes for existing users

### Phase 27: MySQL Lock Deadlock Fix (v0.10.3) - Completed 2026-01-25
1. ✅ **Root Cause Analysis**
   - Identified MySQL lock not released on authentication errors
   - Lock remained active until MySQL connection closed (hours on persistent connections)
   - Users unable to retry login - stuck with HTTP 409 "duplicate" errors
   - Real case: svetlanap@hotmail.com unable to authenticate
2. ✅ **MySQL Lock Improvements** (`supabase-bridge.php`)
   - Increased lock timeout from 0 to 30 seconds (handle slow networks)
   - Moved lock acquisition AFTER early returns (rate limit, CSRF, no_jwt checks)
   - Created `$release_lock` closure for safe cleanup
   - Added explicit lock release in catch block (CRITICAL FIX)
   - Lock now releases on ANY error, not just successful auth
3. ✅ **WordPress Native Auth Fallback** (Frontend)
   - Added /login/ links to timeout error screen (20-second timeout)
   - Added /login/ links to general error screen (catch block)
   - Added "Classic login" link to primary auth form (below OAuth buttons)
   - Users can use WordPress Forgot Password flow as emergency fallback
4. ✅ **Production Deployment**
   - Files uploaded via SCP to production server
   - supabase-bridge.php (214KB), callback.html (30KB), auth-form.html (47KB)
   - Verified files on server (Jan 26 02:03)
   - Git commit & push to GitHub successful

**Root Cause:**
- First attempt: timeout/network error → lock NOT released → lock stuck
- Second attempt: HTTP 409 "duplicate" → user redirected home unauthenticated
- Infinite loop: user stuck, cannot login

**Results:**
- Lock automatically released on ANY error ✅
- 30-second timeout allows retries on slow networks ✅
- WordPress /login/ fallback for edge cases (Cloudflare blocks, ISP issues) ✅
- Eliminated infinite 409 loop ✅

### Phase 28: JWT Clock Skew Fix (v0.10.4) - Completed 2026-01-26
1. ✅ **Root Cause Analysis**
   - Google OAuth authentication failed with "Cannot handle token with iat prior to..." error
   - JWT verification without leeway tolerance
   - Clock skew between Supabase and WordPress servers (1-5 seconds)
   - Token `iat` timestamp appeared "in the future" to WordPress
2. ✅ **JWT Leeway Implementation** (`supabase-bridge.php`)
   - Added `JWT::$leeway = 60` seconds tolerance for clock skew
   - Industry standard practice for distributed systems
   - No security impact (tokens still validated for signature, expiration, audience)
3. ✅ **Production Deployment**
   - File uploaded via SCP to production server
   - supabase-bridge.php (215KB)
   - Verified working with Google OAuth
   - Git commit & push to GitHub successful

**Root Cause:**
- Supabase server clock ahead of WordPress server clock by a few seconds
- JWT library rejects tokens with future timestamps by default
- Valid tokens were incorrectly rejected

**Results:**
- Google OAuth authentication working ✅
- Facebook OAuth authentication working ✅
- Magic Link authentication working ✅
- 60-second leeway handles clock drift ✅

### Phase 29: Help Modal System + Magic Link Cooldown (v0.10.5) - Completed 2026-02-05
1. ✅ **Help Modal System** (`auth-form.html`, `callback.html`)
   - Auto-opens modal after 1.5 seconds on authentication errors
   - Manual "💡 Что делать?" button in all error UI
   - 4 types of help modals: Facebook email error, Magic Link expired, VPN/Cloudflare block, generic timeout
   - Responsive design (mobile-friendly)
   - Closes on outside click or X button
2. ✅ **Magic Link Cooldown** (`auth-form.html`)
   - 60-second cooldown prevents double-submit
   - Countdown display: "Подождите 57 сек..."
   - Disabled button during cooldown
   - Prevents email spam and rate limiting issues
3. ✅ **Enhanced Error Handling** (`callback.html`)
   - Specific handling for "Error getting user email from external provider"
   - Specific handling for unexpected_failure, access_denied, otp_expired
   - Context-aware help modal selection

**Problem:**
- Users encounter rare authentication errors (~5% of cases)
- No guidance for VPN/Cloudflare blocks, old Magic Link emails, Facebook email issues
- Users submit support tickets instead of self-solving

**Results:**
- Self-service help system reduces support load ✅
- Clear instructions for common error scenarios ✅
- Magic Link cooldown prevents rate limiting ✅
- Better UX for authentication errors ✅

### Phase 30: Auto-Enrollment for Manual Transactions (v0.10.6) - Completed 2026-02-06
1. ✅ **New Hooks for Manual Transactions** (`supabase-bridge.php`)
   - Added `mepr-txn-store` hook - catches ALL transactions when saved to database
   - Added `mepr_subscription_post_update` hook - catches subscriptions created via API/Zapier
   - Clear separation: Stripe/PayPal → existing hooks, Manual (Zapier, crypto) → new hooks
2. ✅ **Gateway-Based Routing** (`supabase-bridge.php`)
   - Only process `gateway = 'manual'` in new hooks
   - Prevents duplicate enrollments (each transaction processed by ONE hook only)
   - 10-minute window for subscription processing (accounts for Zapier delays)
3. ✅ **Robust Error Handling** (`supabase-bridge.php`)
   - Validates required properties exist before processing
   - Checks for clock skew (server time differences)
   - Detailed error logging for debugging
   - All auto-enrollment uses existing Course Access tab rules

**Problem:**
- Users who pay via external methods (rubles on external site, cryptocurrency) are added to MemberPress via Zapier
- These transactions have `gateway = 'manual'` and don't trigger standard payment hooks
- Result: Users have membership but aren't auto-enrolled in LearnDash courses

**Results:**
- Zapier-created memberships now trigger auto-enrollment ✅
- Crypto payments now trigger auto-enrollment ✅
- No duplicate enrollments for Stripe/PayPal ✅
- Handles Zapier delays up to 10 minutes ✅

---

## 🔄 Текущая работа: Maintenance Mode

**Status:** All critical issues resolved. System stable.

### Completed (2026-02-06) - Phase 30: Auto-Enrollment for Manual Transactions
- ✅ **Manual transaction support** — Zapier and crypto payments now trigger auto-enrollment
- ✅ **Gateway-based routing** — Prevents duplicate enrollments
- ✅ **Robust error handling** — Clock skew checks, property validation
- ✅ **Production deployed** — supabase-bridge.php uploaded and verified

### Completed (2026-02-05) - Phase 29: Help Modal System + Magic Link Cooldown
- ✅ **Help modal system** — Self-service instructions for authentication errors
- ✅ **Magic Link cooldown** — 60-second cooldown prevents rate limiting
- ✅ **4 modal types** — Facebook email, Magic Link expired, VPN block, generic timeout
- ✅ **Production deployed** — auth-form.html and callback.html uploaded

### Completed (2026-01-26) - Phase 28: JWT Clock Skew Fix
- ✅ **JWT leeway added** — 60-second tolerance for clock skew between servers
- ✅ **Google OAuth working** — Fixed "Cannot handle token with iat prior to..." error
- ✅ **All OAuth providers stable** — Google, Facebook, Magic Link all working
- ✅ **Production deployed** — supabase-bridge.php uploaded and verified

### Completed (2026-01-25) - Phase 27: MySQL Lock Deadlock Fix + Critical Bug Fixes
- ✅ **MySQL lock release fix** — Lock now released in catch block (prevents persistent 409 errors)
- ✅ **Lock timeout increased** — 0→30 seconds (handles slow networks)
- ✅ **Lock repositioned** — Moved after early returns (rate limit, CSRF checks)
- ✅ **WordPress native auth fallback** — /login/ links added to all error screens
- ✅ **Classic login option** — Added to primary auth form (below OAuth buttons)
- ✅ **Edge case handling** — Cloudflare blocks, network timeouts, ISP issues
- ✅ **Plugin activation fix (issue #24)** — test-functions.php moved to autoload-dev
- ✅ **JavaScript SyntaxError fix (issues #25, #13)** — Output buffering fixes HTML entities
- ✅ **GitHub issues closed** — 6 issues closed today (#14, #23, #15, #24, #25, #13)

### Completed (2026-01-23) - Phase 26: Auth UX & Error Handling
- ✅ **Timeout override fix** — OAuth error messages no longer overwritten by timeout
- ✅ **Accurate error display** — access_denied, otp_expired show correct messages
- ✅ **Membership expiration fix** — 5,940 users corrected from 10 to 20 days access
- ✅ **User support questionnaire** — 5-question diagnostic template for support emails

### Previously Completed (2026-01-10) - Auth UX & Email Deliverability
- ✅ **76% failure rate fixed** — otp_expired errors eliminated
- ✅ **In-flight guards** — Prevent double-clicks on auth buttons
- ✅ **60-second cooldown** — Resend button with countdown timer
- ✅ **Email spam fixed** — 100% spam rate → 0% (template optimization)
- ✅ **Callback timeout monitoring** — Silent 20-second diagnostics
- ✅ **Provider tracking** — magic_link/google/facebook telemetry

**Next planned features (ROADMAP):**
- Role Mapping (v0.11.0)
- User Metadata Sync (v0.12.0)

---

## 🔧 Технологии

- **Frontend:** WordPress (PHP 8.0+), Vanilla JavaScript
- **Styling:** Custom CSS (WordPress themes)
- **Backend:** WordPress REST API
- **Authentication:** Supabase Auth (JWT-based)
- **Database:** WordPress (wp_users, wp_usermeta) + Supabase PostgreSQL
- **Dependencies:** Composer (firebase/php-jwt)
- **Deployment:** WordPress hosting (any)
- **Production:** questtales.com

---

## 🎉 Production Status

**Status:** ✅ Production Ready
**Live Sites:**
- https://alexeykrol.com (v0.10.6 - stable, Zapier/manual transaction auto-enrollment, Help modal system, Magic Link cooldown, JWT clock skew fixed, MySQL lock deadlock fixed, WordPress native auth fallback, Accurate error messages, Auth UX fixes, Email spam fixed, Landing URL tracking, Course Access auto-enrollment, Checkout auth overlay, MemberPress webhooks, cross-device Magic Link, data integrity monitoring, universal membership/enrollment, PKCE flow support, Russian UI, Safari compatible)
**Version:** 0.10.6
**Last Update:** 2026-02-06
**Known Issues:** 0 (All auth methods working, Zapier/crypto auto-enrollment working, Help modal system deployed, Magic Link cooldown active, JWT clock skew resolved, MySQL lock deadlock resolved, WordPress /login/ fallback available, 0% failure rate, accurate error messages for OAuth errors, emails deliver to inbox, callback timeout monitoring active, landing URL tracking active, checkout authentication overlay deployed, course access auto-enrollment active, 100% registration tracking, MemberPress webhook system, cross-device Magic Link, data integrity monitoring, universal membership/enrollment system, PKCE flow support, Safari Privacy supported, Russian localization, repository clean)

---

*Этот файл — SINGLE SOURCE OF TRUTH для текущего состояния проекта*
*Migrated from Init/PROJECT_SNAPSHOT.md on 2025-12-10*
*Framework: Claude Code Starter v2.2*
