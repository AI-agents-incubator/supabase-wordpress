# BACKLOG — Supabase Bridge

*Framework: Claude Code Starter v2.3.1*
*Last Updated: 2026-02-06*

---

## Current Status

**Version:** 0.10.6 (Production Ready)
**Phase:** Maintenance

---

## Active Sprint

### Completed - v0.10.6 Release (2026-02-06)
- [x] Fixed auto-enrollment for Zapier/manual transactions (gateway = 'manual')
- [x] Added mepr-txn-store and mepr_subscription_post_update hooks
- [x] Gateway-based routing prevents duplicate enrollments
- [x] 10-minute window for subscription processing
- [x] Robust error handling for clock skew and missing properties
- [x] All manual transactions now trigger Course Access auto-enrollment

### Completed - v0.10.5 Release (2026-02-05)
- [x] Implemented Help Modal System for authentication errors
- [x] Added 4 types of help modals (Facebook email, Magic Link expired, VPN/Cloudflare block, generic timeout)
- [x] Auto-opens modal after 1.5 seconds on error
- [x] Manual "💡 Что делать?" button in all error UI
- [x] Added Magic Link cooldown (60 seconds)
- [x] Prevents double-submit with countdown: "Подождите 57 сек..."
- [x] Responsive design (mobile-friendly)

### Completed - v0.10.4 Release (2026-01-26)
- [x] Fixed JWT clock skew issue ("Cannot handle token with iat prior to..." error)
- [x] Added JWT::$leeway = 60 seconds for clock drift tolerance
- [x] Tested Google OAuth authentication - working ✅
- [x] Tested Facebook OAuth authentication - working ✅
- [x] Tested Magic Link authentication - working ✅
- [x] Deployed to production and verified
- [x] Updated version to 0.10.4 in supabase-bridge.php
- [x] Updated CHANGELOG.md with v0.10.4 release notes
- [x] Updated .claude/SNAPSHOT.md with Phase 28 completion
- [x] Updated .claude/BACKLOG.md to version 0.10.4

### Completed - v0.10.3 Release (2026-01-25)
- [x] Fixed MySQL lock not released in catch block (root cause of persistent 409 errors)
- [x] Increased lock timeout from 0 to 30 seconds (handle slow networks)
- [x] Moved lock acquisition after early returns (rate limit, CSRF checks)
- [x] Added WordPress native auth fallback (/login/) on all error screens
- [x] Added classic login link to primary auth form (below OAuth buttons)
- [x] Fixed plugin activation fatal error (issue #24 - test-functions.php in production autoload)
- [x] Fixed JavaScript SyntaxError in auth form (issues #25, #13 - HTML entities in inline JS)
- [x] Added output buffering hook to fix &#038;&#038; → && in <script> tags
- [x] Closed 6 GitHub issues (#14, #23, #15, #24, #25, #13)
- [x] Deployed to production and verified files uploaded successfully
- [x] Updated version to 0.10.3 in supabase-bridge.php
- [x] Updated version to 0.10.3 in .claude/BACKLOG.md
- [x] Created git tag v0.10.3
- [x] Created GitHub Release v0.10.3 (Latest)
- [x] Synced all metafiles to version 0.10.3

### Completed - Callback Error Handling Fix (2026-01-23)
- [x] Fixed timeout message overwriting OAuth error messages
- [x] Added clearTimeout() to cancel 20-second timeout when errors detected
- [x] Users now see accurate error messages (access_denied, otp_expired, etc.)
- [x] Improved troubleshooting via user-reported error messages
- [x] Fixed membership expiration dates (20 days instead of 10) for 5,940 users

### Completed - Auth UX Fixes & Email Deliverability (2026-01-10)
- [x] Fixed 76% auth failure rate (otp_expired errors)
- [x] Added in-flight guards to email submit and OAuth buttons (prevent double-clicks)
- [x] Added 60-second cooldown on resend button with countdown timer
- [x] Added loading states: "Отправляем...", "Перенаправляем..."
- [x] Added critical messaging about using newest email
- [x] Implemented silent 20-second callback timeout monitoring
- [x] Added callback_timeout telemetry with diagnostic stage tracking
- [x] Updated error messages to discourage immediate retry attempts
- [x] Added provider tracking (magic_link/google/facebook) to telemetry
- [x] Fixed Magic Link emails landing in spam (100% spam → 0% spam)
- [x] Documented email template spam filter best practices in README
- [x] Renamed test-no-elem-2-wordpress-paste.html → callback.html
- [x] Removed internal Supabase files from GitHub repository
- [x] Result: 0 failures in 20+ minutes after deploy (was 12 failures/45min)

### Completed - Telemetry System v0.12.0 (2026-01-10)
- [x] Implemented Data Quality Check as Step 0 (validate telemetry before analyzing failures)
- [x] Added `fetch_auth_stats()` - fetches data from Supabase Auth (source of truth)
- [x] Added `calculate_telemetry_stats()` - calculates telemetry metrics
- [x] Added `data_quality_check()` - compares Auth vs Telemetry using two criteria
- [x] Criterion 1: Successful logins (`last_sign_in_at` in Auth = `auth_success` in Telemetry)
- [x] Criterion 2: Incomplete requests (waiting users = requested - completed)
- [x] Updated Claude prompt to validate telemetry correctness FIRST
- [x] Changed analysis window to 1 hour (was 3 hours)
- [x] Fixed auth_success tracking - added SUPABASE_CFG to callback page shortcode
- [x] Updated Telemetry tab in WordPress to v0.12.0 (1 hour window, last_sign_in_at, failure rate)
- [x] Fixed sb_cfg() key mapping for SERVICE_ROLE_KEY
- [x] Uploaded all files to production

### Completed - Security Cleanup Optimization (2026-01-10)
- [x] Updated `security/cleanup-dialogs.sh` with `--last` flag (clean only last dialog)
- [x] Added Supabase credentials cleanup (project ID, JWT tokens)
- [x] Added `questtales` pattern cleanup
- [x] Updated Cold Start Protocol (Step 0.5) to use `--last`
- [x] Updated Completion Protocol (Step 3.5) to use `--last`
- [x] Updated sec24 agent with Step 0 (cleanup before scan)
- [x] Updated sec24 to scan only LAST dialog (not all 300+)

### Next Steps - Telemetry Refactoring
- [ ] Remove Telemetry tab from WordPress plugin (internal tool, not for end users)
- [ ] Create local Node.js dashboard for telemetry analysis
- [ ] All analytics should run locally using telemetry-analyzer.env credentials

### Completed (v0.10.1) - Landing URL Marketing Tracking
- [x] Added `landing_url` TEXT column to Supabase `wp_user_registrations` table
- [x] Created SQL migration file (`supabase/add-landing-url-field.sql`)
- [x] Implemented `cleanTrackingParams()` function in `auth-form.html` to remove Facebook/Google tracking parameters
- [x] Captured `document.referrer` as LANDING_URL on auth form page
- [x] Modified Magic Link flow to pass `landing_url` via URL parameter (cross-device compatible)
- [x] Modified OAuth flow to save `landing_url` to localStorage (same-device)
- [x] Updated callback handler (`callback.html`) to read landing_url from URL param or localStorage
- [x] Modified `sb_log_registration_to_supabase()` function to accept optional `$landing_url` parameter
- [x] Added landing_url validation via `sb_validate_url_path()`
- [x] Included landing_url in Supabase INSERT payload
- [x] Deployed to production and tested with real Facebook ads traffic
- [x] Verified UTM parameter tracking (`?utm=afb_0003`, `?utm=fbp_001`, etc.)
- [x] Confirmed 100% landing URL attribution for new registrations

### Completed (v0.9.13) - MemberPress Webhook System Upgrade
- [x] Renamed webhook system from "Make.com" to "MemberPress Webhooks"
- [x] Changed all function names: `sb_make_webhook_*` → `sb_memberpress_webhook_*`
- [x] Updated admin UI tab: "🎣 Make.com" → "🎣 MemberPress Webhook"
- [x] Implemented multiple webhook URL support (textarea input, one URL per line)
- [x] Rewrote webhook send function to support Make.com, Zapier, n8n simultaneously
- [x] Implemented MemberPress-compatible payload format (100+ fields)
- [x] Created automatic migration system (`sb_migrate_webhook_settings`)
- [x] Added backward compatibility wrapper (`sb_send_make_webhook`)
- [x] Implemented fallback logic to read old options if new ones are empty
- [x] Modified test function to use REAL data from last registration (not stub data)
- [x] Added real payload preview in documentation section (like MemberPress)
- [x] Fixed success message to show dynamic details (user email, transaction ID)
- [x] Deployed to production with zero downtime
- [x] Verified auto-migration successful and existing Make.com automations working

### Completed (v0.9.12) - Data Integrity Monitoring & Cross-Device Bug Fixes
- [x] Fixed Magic Link cross-device registration URL loss (OAuth redirects lose localStorage)
- [x] Pass registration_url via URL query parameter in Magic Link emails
- [x] Update callback page to read registration_url from URL with localStorage fallback
- [x] Fixed missing registration pair sync calls (sb_sync_pair_to_supabase, sb_delete_pair_from_supabase)
- [x] Created data integrity monitoring system (monitoring/check-integrity.sh)
- [x] Added monitoring documentation and setup files (README.md, .env.example)
- [x] Verified 100% pair_id tracking accuracy after fixes
- [x] Added .production-credentials to .gitignore with Supabase credentials section

### Completed (v0.9.11) - Universal Membership & Enrollment
- [x] Added helper functions for membership/enrollment checks (`sb_has_membership`, `sb_is_enrolled`)
- [x] Implemented User Status Analyzer module (analyzes memberships/enrollments)
- [x] Implemented Action Executor module (executes assignments)
- [x] Fixed redirect logic conflict (Registration Pairs vs Return URL)
- [x] All integration tests passed successfully

### Completed (v0.9.10) - PKCE Flow Support
- [x] Added PKCE flow support to callback handler (both hash fragment and query string)
- [x] Fixed dotsTimer bug in callback handler (ReferenceError: countdownTimer)
- [x] Investigated OAuth flow changes in Supabase SDK
- [x] Rollback and re-apply Phase 19 changes with fixes
- [x] Tested OAuth in Chrome, Safari, Firefox - all browsers working
- [x] Documented floating CDN dependency issue and solution

### Completed (v0.9.9) - Safari Privacy & UX Polish
- [x] Safari Privacy Protection (safeStorage wrapper with in-memory fallback)
- [x] Russian localization for all UI elements
- [x] UX improvements - eliminated flickering screens
- [x] 3-step troubleshooting instructions in footer
- [x] Instant loading screen for callback page
- [x] Animated dots instead of countdown timer
- [x] Security incident response - SSH keys removed from git history
- [x] Repository cleanup - removed 51 debug files (-20,315 lines)
- [x] Reorganized structure (security/, supabase/ folders)
- [x] CLAUDE.md Completion Protocol improvements
- [x] README.md fundamental overhaul

### Completed (v0.9.8)
- [x] Comprehensive security scanning system (bash-based credential detection)
- [x] Automated dialog file cleanup script with [REDACTED] markers
- [x] Integration testing for Registration Pairs, MemberPress, LearnDash
- [x] Unified test runner with smoke tests, unit tests, security scanning
- [x] LearnDash banner patch OPcache improvements
- [x] Git history cleanup with BFG Repo-Cleaner (removed all credentials)
- [x] .gitignore improvements (wildcard rules for dialog files)

### Completed (v0.9.7)
- [x] Return-to-Origin Login Flow - user returns to page where they clicked "Login"
- [x] Implemented `document.referrer` tracking on login page (localStorage)
- [x] Added redirect logic to callback handler (reads from localStorage)
- [x] Created `[supabase_auth_callback]` shortcode for unified architecture
- [x] Unified shortcode system - both auth pages use shortcodes for automatic updates
- [x] Tested Google OAuth login from multiple pages
- [x] Tested Facebook OAuth login from multiple pages
- [x] Tested Magic Link login from multiple pages
- [x] Verified in Chrome, Safari, Firefox (normal + incognito modes)

### Completed (v0.9.6)
- [x] Two-Page Authentication Architecture Refactoring
- [x] Analyzed Chrome/Safari hash detection issue - found duplicate callback code
- [x] Implemented two-page architecture: form page + callback handler
- [x] Created dedicated callback page `/test-no-elem-2/` with clean handler
- [x] Added `redirect_to` parameter support for login redirects
- [x] Removed ~112 lines of duplicate callback code from `auth-form.html`
- [x] Separated concerns: form display vs authentication processing
- [x] Fixed OAuth redirect URLs to point to callback page
- [x] Tested in Chrome, Safari, Firefox (normal + incognito modes)
- [x] Verified Google OAuth and Facebook OAuth login flows work correctly

### Completed (v0.9.1)
- [x] LearnDash Banner Management UI - WordPress Admin tab for banner patch control
- [x] New "🎓 Banner" tab with checkbox to enable/disable banner removal
- [x] Real-time patch status indicator with color-coded badges
- [x] One-click apply/restore via AJAX with automatic backups
- [x] Warning notifications after LearnDash updates
- [x] Backward compatible with old patch versions

### Completed (v0.9.0)
- [x] MemberPress Integration - Auto-assign FREE memberships on registration
- [x] New Memberships tab with CRUD operations
- [x] LearnDash Integration - Auto-enroll users in courses on registration
- [x] New Courses tab with CRUD operations
- [x] LearnDash banner removal patch script (idempotent, upgrade-safe)
- [x] Remove redundant Supabase sync for Registration Pairs
- [x] Test all integrations with MemberPress 1.x and LearnDash 4.x

### Completed (v0.8.5)
- [x] Fix Registration Pairs tracking accuracy (Referer → explicit POST param)
- [x] Implement Edit Pair functionality
- [x] Add custom delete confirmation modal (Safari compatible)
- [x] Fix registration logging bug (remove thankyou_page_url column)
- [x] Improve HTTP 409 duplicate callback handling
- [x] Add RLS policies for anon role on registration tables

*No active development tasks. Project is in maintenance mode.*

---

## Next Up

When development resumes, pick from ROADMAP.md:

1. **v0.10.0 - Role Mapping** (High priority)
2. **v0.11.0 - User Metadata Sync** (High priority)
3. **v0.12.0 - Email/Password Auth** (Medium priority)

---

## Technical Debt

### High Priority
- [ ] PHPStan analysis — static type checking
- [ ] WordPress Coding Standards (WPCS) compliance
- [ ] Unit tests for JWT verification
- [ ] Integration tests with mock Supabase

### Medium Priority
- [ ] Video tutorial (YouTube)
- [ ] Supabase RLS examples
- [ ] Troubleshooting guide

### Low Priority
- [ ] Email Delivery UX Improvements (Phase 23) — See EMAIL_DELIVERY_ANALYSIS.md
  - Frontend rate limiting (60 sec cooldown)
  - Email typo detection for common domains
  - UX improvements (instructions, countdown, OAuth promotion)
  - Backend bounce check API
  - Supabase bounce tracking table
- [ ] Lazy script loading (only on login pages)
- [ ] Minified inline JS
- [ ] Database query optimization

---

## Known Issues

*No open issues. All resolved issues archived in git history.*

---

## References

- **Strategic planning:** [ROADMAP.md](./ROADMAP.md)
- **Release history:** [CHANGELOG.md](../CHANGELOG.md)
- **Architecture:** [ARCHITECTURE.md](./ARCHITECTURE.md)
- **Ideas:** [IDEAS.md](./IDEAS.md)

---

*This file tracks current work only*
*For history, see CHANGELOG.md*
*For future plans, see ROADMAP.md*
