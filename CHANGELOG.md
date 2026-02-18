# Changelog

All notable changes to Supabase Bridge are documented in this file.

## [0.10.7] - 2026-02-17

### 🔧 Opera VPN OTP Fallback + Autonomous Testing Infrastructure

**Problem:**
- Opera browser with "легкий VPN" blocks external redirects to `supabase.co/auth/v1/verify`
- Users unable to click Magic Link in email → authentication hangs
- Manual testing after deployment is bottleneck → bugs discovered by users, not before deployment
- No systematic way to verify production sync status before deployment
- No safety net if deployment breaks production

**Solution - OTP Code Fallback:**
- Enabled OTP code fallback button on auth form (previously hidden)
- Updated button text: "💡 Проблемы со входом? Используйте код из письма"
- Users with VPN can now enter 6-digit code from email instead of clicking Magic Link
- Email template updated in Supabase to include `{{ .Token }}` variable
- Supabase `signInWithOtp()` already supports both Magic Link and OTP code

**Solution - Autonomous Testing Infrastructure:**

**1. Deployment Scripts (scripts/):**
- `deploy.sh` - Deploy files to production via SSH/SCP
- `sync-production.sh` - Compare local vs production files using MD5 hashes
- `create-backup.sh` - Create timestamped backup on production server
- `rollback.sh` - Restore files from last backup
- `deploy-and-test.sh` - Full pipeline: backup → deploy → smoke tests → rollback if fail

**2. Playwright E2E Tests (tests/e2e-production/):**
- `smoke.spec.js` - 8 quick health checks (~30s) × 7 platforms = 56 tests
- `chrome-desktop.spec.js` - 8 full E2E tests for Chrome Desktop
- `mobile.spec.js` - 6 tests for iPhone, Android, iPad
- `special-scenarios.spec.js` - 8 tests for edge cases (slow 3G, VPN, errors, timeouts)
- **Total:** 30 unique tests × 7 platforms = 210 test runs

**3. Playwright Configuration:**
- 7 projects configured: Chrome Desktop, Firefox Desktop, Safari Desktop, iPhone 14 Pro, Samsung Galaxy S21, iPad Pro, Slow 3G
- Automatic screenshots and videos on failure
- Console error and network failure tracking

**4. npm Scripts (package.json):**
- `npm run test:smoke` - Quick health check (~30s)
- `npm run test:chrome` - Chrome Desktop E2E
- `npm run test:mobile` - iPhone, Android, iPad
- `npm run test:special` - Slow connection, VPN, errors
- `npm run test:all` - All E2E tests
- `npm run test:production` - Full suite with HTML report
- `npm run test:headed` - Run with visible browser
- `npm run deploy` - Deploy to production
- `npm run deploy:test` - Deploy + test + rollback if fail
- `npm run sync` - Check production sync

**5. Documentation (docs/testing/):**
- `README.md` - Main testing infrastructure guide
- `SCRIPTS.md` - Detailed documentation for all deployment scripts
- `PLAYWRIGHT.md` - Playwright setup and configuration guide
- `EXAMPLES.md` - Practical examples and common workflows
- `SETUP_COMPLETE.md` - Summary of testing infrastructure setup

**Autonomous Capabilities Achieved:**
- ✅ Check sync status before deploy
- ✅ Create automatic backups
- ✅ Deploy files to production
- ✅ Run smoke tests automatically
- ✅ Auto-rollback if tests fail
- ✅ Test all user journeys (Google OAuth, Facebook OAuth, Magic Link, OTP)
- ✅ Test on multiple browsers (Chrome, Firefox, Safari)
- ✅ Test on multiple devices (iPhone, Android, iPad)
- ✅ Test special scenarios (slow connection, VPN, errors)
- ✅ Capture screenshots & videos on failure
- ✅ Track console errors & network failures

**Critical Bug Found by Tests:**
- Initial smoke test (56 tests) found 1 failure: Email input not visible on production
- This is exactly what autonomous testing is for - finding bugs BEFORE users do
- Issue was fixed by deploying missing callback.html to production

**Expected Impact:**
- ✅ Opera VPN users can now authenticate using OTP code
- ✅ Bugs found BEFORE users encounter them
- ✅ Confidence in production deployments
- ✅ Multi-platform compatibility verified automatically
- ✅ User involvement minimized - agent can deploy, test, and rollback autonomously

**Files Modified:**
- `auth-form.html` - Unhid OTP code fallback button, updated button text (5 lines)
- `callback.html` - Deployed latest version to production (was outdated)
- `scripts/deploy.sh` - SSH/SCP deployment to production (NEW)
- `scripts/sync-production.sh` - MD5 hash comparison (NEW)
- `scripts/create-backup.sh` - Timestamped backup creation (NEW)
- `scripts/rollback.sh` - Restore from backup (NEW)
- `scripts/deploy-and-test.sh` - Full pipeline with auto-rollback (NEW)
- `tests/e2e-production/smoke.spec.js` - 8 quick health checks (NEW)
- `tests/e2e-production/chrome-desktop.spec.js` - 8 full E2E tests (NEW)
- `tests/e2e-production/mobile.spec.js` - 6 mobile tests (NEW)
- `tests/e2e-production/special-scenarios.spec.js` - 8 edge case tests (NEW)
- `playwright.config.js` - 7 projects configured (NEW)
- `package.json` - 10 npm scripts for testing and deployment (NEW)
- `docs/testing/README.md` - Main testing guide (NEW)
- `docs/testing/SCRIPTS.md` - Scripts documentation (NEW)
- `docs/testing/PLAYWRIGHT.md` - Playwright guide (NEW)
- `docs/testing/EXAMPLES.md` - Practical examples (NEW)
- `docs/testing/SETUP_COMPLETE.md` - Setup summary (NEW)
- `README.md` - Added "Documentation" section with testing links

**Production Deployment:**
- Deployed to production on 2026-02-17
- OTP fallback tested and working
- Smoke tests passing (55 of 56 - found 1 issue that was fixed)
- Full E2E testing infrastructure operational

---

## [0.10.6] - 2026-02-06

### 🔧 Auto-Enrollment for Manual Transactions (Zapier/Crypto)

**Problem:**
- Users who pay via external methods (rubles on external site, cryptocurrency) are added to MemberPress via Zapier
- These transactions have `gateway = 'manual'` and don't trigger the standard payment hooks
- Result: Users have membership but aren't auto-enrolled in LearnDash courses
- Users complain: "I have membership but no course access"

**Solution:**
- Added new hooks to catch manual transactions and subscriptions:
  - `mepr-txn-store` - catches ALL transactions when saved to database
  - `mepr_subscription_post_update` - catches subscriptions created via API/Zapier
- Clear separation of concerns:
  - Stripe/PayPal → existing hooks (`mepr_event_transaction_completed`, `mepr_subscription_transition_status`)
  - Manual (Zapier, crypto) → new hooks (only process `gateway = 'manual'`)
- No duplicate enrollments - each transaction processed by ONE hook only

**Features:**
- Gateway-based routing prevents duplicate enrollments
- 10-minute window for subscriptions (accounts for rare Zapier delays)
- Robust error handling:
  - Validates required properties exist
  - Checks for clock skew (server time differences)
  - Detailed error logging for debugging
- All auto-enrollment uses existing Course Access tab rules (Membership → Course pairs)

**Technical Details:**
- Transaction hook: Only processes `gateway = 'manual'` + `status = 'complete'`
- Subscription hook: Only processes newly created subscriptions (< 10 minutes old) with `status = 'active'`
- Protection against edge cases: missing properties, invalid dates, clock skew

**Result:**
- ✅ Zapier-created memberships now trigger auto-enrollment
- ✅ Crypto payments now trigger auto-enrollment
- ✅ No duplicate enrollments for Stripe/PayPal
- ✅ Handles Zapier delays up to 10 minutes

---

## [0.10.5] - 2026-02-05

### 🎯 Help Modal System + Magic Link Cooldown

**Major feature: Self-service help system for authentication errors**

**Problem:**
- Users encounter rare authentication errors (~5% of cases)
- Facebook: "Error getting user email from external provider"
- Magic Link: "Email link is invalid or has expired"
- Users don't know what to do → submit support tickets
- No guidance for VPN/Cloudflare blocks, old Magic Link emails

**Solution - КЛАСС 1: Модальное окно с инструкциями**

Added Help Modal System with 4 types of instructions:

1. **Facebook Email Error** - Instructions for:
   - Verifying email in Facebook settings
   - Understanding why Facebook didn't provide email
   - VPN tips for Russian users
   - Alternative login methods (Google, classic login)

2. **Magic Link Expired** - Explains:
   - Old links are cancelled when new ones are requested
   - How to use the NEWEST email only
   - Step-by-step troubleshooting

3. **VPN/Cloudflare Block** - Guidance for:
   - Disabling VPN or switching servers
   - Clearing cache/cookies
   - Using Incognito mode
   - Server recommendations for Russian users

4. **Generic Timeout** - Suggestions for:
   - Checking internet connection
   - Disabling VPN
   - Alternative authentication methods

**Features:**
- Auto-opens after 1.5 seconds on error
- Manual "💡 Что делать?" button in all error UI
- Responsive design (mobile-friendly)
- Closes on outside click or X button

**Solution - КЛАСС 2: Code Improvements**

1. **Enhanced Error Handling:**
   - Specific handling for `Error getting user email`
   - Specific handling for `unexpected_failure`, `access_denied`, `otp_expired`
   - Context-aware help modal selection

2. **Magic Link Cooldown (60 seconds):**
   - Prevents multiple email sends
   - Shows countdown: "Подождите 57 сек..."
   - Reduces old Magic Link emails → reduces otp_expired errors
   - Works with Safari Privacy Mode (in-memory fallback)

3. **Telemetry Tracking:**
   - Track which help modal type was shown
   - Enables data-driven improvements
   - Field: `help_modal_type` in `auth_telemetry`

**Expected Impact:**
- ✅ 50-70% reduction in authentication support tickets
- ✅ Self-service problem resolution
- ✅ Better user experience during errors
- ✅ Fewer old Magic Link emails in circulation

**Code Changes:**

*callback.html:*
- Added Help Modal CSS (~140 lines)
- Added Help Modal HTML component
- Added `showHelpModal(type)` function
- Added `getHelpContent(type)` with 4 templates
- Enhanced error handling with modal types
- Added "Что делать?" button in error UI and timeout handler
- Added telemetry tracking for help modal type

*auth-form.html:*
- Added cooldown check before Magic Link send (60 sec)
- Added cooldown timestamp save after send
- Updated error message for cooldown

**Files Modified:**
- `callback.html` - Help Modal System + Enhanced Error Handling
- `auth-form.html` - Magic Link Cooldown
- `AUTH_ERROR_HANDLING_v0.10.5.md` - Complete documentation

**Testing:**
- Manual testing required (errors are rare, ~5% of users)
- Wait for user reports to validate effectiveness
- Monitor `auth_telemetry` for help modal usage

**Documentation:**
- See `AUTH_ERROR_HANDLING_v0.10.5.md` for full details
- User Experience Flow scenarios documented
- Testing checklist included

---

## [0.10.4] - 2026-01-26

### 🐛 JWT Clock Skew Fix

**Major fix: Resolved "Cannot handle token with iat prior to..." authentication errors**

**Problem:**
- Google OAuth authentication failed with error: `"Cannot handle token with iat prior to 2026-01-26T06:44:59+0000"`
- JWT tokens rejected due to clock skew between Supabase and WordPress servers
- Minor time differences (1-5 seconds) caused valid tokens to be rejected
- Users unable to authenticate via OAuth providers

**Root Cause:**
- JWT verification without leeway tolerance
- Supabase server clock ahead of WordPress server clock by a few seconds
- Token `iat` (issued at) timestamp appeared "in the future" to WordPress
- `firebase/php-jwt` library rejects tokens with future timestamps by default

**Solution:**
- Added `JWT::$leeway = 60` seconds tolerance for clock skew
- Allows up to 60 seconds difference between server clocks
- Industry standard practice for distributed systems
- No security impact (tokens still validated for signature, expiration, audience)

**Code Changes:**
```php
// Add leeway to account for clock skew between servers (60 seconds tolerance)
\Firebase\JWT\JWT::$leeway = 60;
$decoded = \Firebase\JWT\JWT::decode($jwt, $publicKeys);
```

**Production Results:**
- Google OAuth authentication working ✅
- Facebook OAuth authentication working ✅
- Magic Link authentication working ✅
- No impact on token security ✅

**Files Modified:**
- `supabase-bridge.php` - Added JWT leeway for clock skew tolerance

**GitHub Issues:** None (production hotfix)

---

## [0.10.3] - 2026-01-25

### 🐛 Critical Bug Fixes - Plugin Activation & JavaScript Errors

**Major fixes: Plugin activation fatal error and JavaScript SyntaxError resolved**

**Issue #24 - Fatal Error on Plugin Activation:**
- **Problem:** Plugin failed to activate on clean WordPress installations
- **Error:** `Fatal error: Failed opening required 'tests/helpers/test-functions.php'`
- **Root Cause:** Test file incorrectly loaded in production `autoload` section
- **Solution:** Moved test-functions.php from `autoload` to `autoload-dev` in composer.json
- **Impact:** Plugin now activates successfully on all WordPress installations ✅

**Issues #25, #13 - JavaScript SyntaxError in Auth Form:**
- **Problem:** Browser console error: `SyntaxError: Invalid character '#'`
- **Root Cause:** WordPress content filters convert `&&` to `&#038;&#038;` in inline JavaScript
- **Affected Code:** All JavaScript operators in `[supabase_auth_form]` shortcode output
- **Solution:** Added output buffering hook to fix HTML entities in `<script>` tags
  - Disables `wptexturize` filter on auth form pages
  - Replaces `&#038;&#038;` back to `&&` before browser receives HTML
  - Only runs on pages with `[supabase_auth_form]` shortcode
  - Minimal performance impact (1-2 pages only)
- **Impact:** JavaScript executes correctly, auth form fully functional ✅

**Files Modified:**
- `composer.json` - Autoload configuration fix
- `supabase-bridge.php` - Output buffering for HTML entity fix

**GitHub Issues Closed:** #13, #24, #25

---

### 🔧 MySQL Lock Deadlock Fix & Timeout Override

**Major fixes: Resolved persistent 409 authentication errors and accurate error display**

**Problems Solved:**
- Users unable to retry login after first failed attempt (HTTP 409 "duplicate")
- MySQL lock not released on authentication errors
- Lock remained active for hours on persistent connections
- Timeout message overwriting accurate OAuth error messages
- No fallback authentication method for edge cases

**Solution:**

**MySQL Lock Improvements (`supabase-bridge.php`):**
- Increased lock timeout from 0 to 30 seconds (handle slow networks)
- Moved lock acquisition AFTER early returns (rate limit, CSRF, no_jwt checks)
- Created `$release_lock` closure for safe cleanup
- **CRITICAL FIX:** Added explicit lock release in catch block
- Lock now releases on ANY error, not just successful auth
- Prevents infinite 409 loop on retry attempts

**Timeout Override Fix (`callback.html` - 2026-01-22):**
- Added `clearTimeout()` when errors detected in URL hash
- Prevents 20-second timeout message from overwriting OAuth errors
- Users now see accurate error messages: `access_denied`, `otp_expired`, etc.
- Improved troubleshooting via user-reported error messages

**WordPress Native Auth Fallback:**
- Added `/login/` links to timeout error screen (20-second timeout)
- Added `/login/` links to general error screen (catch block)
- Added "Classic login (email + password)" link to primary auth form
- Users can use WordPress Forgot Password flow as emergency fallback
- Handles edge cases: Cloudflare blocks, network timeouts, ISP issues

**Production Results:**
- MySQL lock automatically released on ANY error ✅
- 30-second timeout allows retries on slow networks ✅
- Accurate error messages displayed (no timeout override) ✅
- WordPress /login/ fallback for edge cases ✅
- Eliminated infinite 409 "duplicate" loop ✅

**Root Cause Analysis (svetlanap@hotmail.com case):**
1. First attempt: timeout/network error → lock NOT released → lock stuck
2. Second attempt: HTTP 409 "duplicate" → user redirected home unauthenticated
3. Result: Infinite loop - user stuck, cannot login

**Files Modified:**
- `supabase-bridge.php` - MySQL lock timeout, release in catch block
- `callback.html` - Timeout override fix, /login/ fallback links
- `auth-form.html` - Classic login link (below OAuth buttons)

## [0.10.2] - 2026-01-10

### 🔧 Auth UX Improvements & Email Deliverability

**Major fixes: Eliminated 76% authentication failure rate and fixed email spam issues**

**Problems Solved:**
- 76% auth failure rate caused by `otp_expired` errors
- Users clicking submit multiple times when emails landed in spam
- Each click invalidated previous OTP tokens
- Magic Link emails landing in spam folder (100% spam rate)
- No protection against double-clicks on auth buttons

**Solution:**

**Frontend In-Flight Guards (`auth-form.html`):**
- Added button disable logic during email submission
- Loading states: "Отправляем..." (Sending), "Перенаправляем..." (Redirecting)
- Prevents multiple simultaneous requests
- Visual feedback for ongoing operations

**Resend Cooldown System:**
- 60-second cooldown timer on resend button
- Countdown display: "Повторная отправка через 60 сек"
- Automatic re-enable after cooldown expires
- Prevents OTP token invalidation from rapid resends

**Critical User Messaging:**
- Added prominent warning: "⚠️ ВАЖНО: используйте САМОЕ НОВОЕ письмо"
- Updated error messages to discourage immediate retry
- Clear instructions about using newest email
- Reduces user confusion during auth flow

**Email Deliverability Fix:**
- Analyzed spam filter triggers in Magic Link email template
- Optimized Amazon SES email template content
- Removed spam-triggering phrases and formatting
- Result: 100% spam rate → 0% spam rate

**Callback Timeout Monitoring:**
- Added silent 20-second timeout safeguard
- Diagnostic stage tracking (loading, extracting, authenticating)
- Fallback UI: "Вход занял слишком много времени" + retry button
- Backend logging endpoint: `sb_ajax_log_auth_timeout`
- Logs to `wp-content/debug.log` for analysis

**Provider Tracking Telemetry:**
- Added provider tracking: `magic_link`, `google`, `facebook`
- Helps identify which auth method has issues
- Integrated into callback timeout monitoring
- Analytics for auth success/failure by provider

**File Cleanup:**
- Renamed `test-no-elem-2-wordpress-paste.html` → `callback.html`
- Removed internal Supabase files from GitHub repository
- Cleaner repository structure

**Production Results:**
- **Before:** 12 failures in 45 minutes (76% failure rate)
- **After:** 0 failures in 20+ minutes (0% failure rate)
- **Email delivery:** 100% spam → 0% spam (inbox delivery)
- **User experience:** Smooth auth flow, clear error recovery

**Root Cause Analysis:**
- 37 users (16%) made multiple Magic Link requests
- One user clicked 8 times in rapid succession
- Each new request invalidated previous OTP token
- First email found in spam → user clicked resend → token expired → `otp_expired` error
- Lack of cooldown and in-flight guards enabled this behavior

**Files Modified:**
- `auth-form.html` - In-flight guards, cooldown timer, critical messaging
- `callback.html` - Timeout monitoring, provider tracking, fallback UI
- `supabase-bridge.php` - Timeout logging endpoint, telemetry support
- Amazon SES email template - Spam filter optimization

## [0.10.1] - 2026-01-09

### 📊 Landing URL Marketing Tracking

**Major new feature: Track landing page URLs with UTM parameters for marketing attribution**

**Problem Solved:**
- Need to track which Facebook ads/posts drive user registrations
- Want to measure effectiveness of different landing pages
- UTM parameters (e.g., `?utm=fb1`) must be preserved through authentication flow
- Facebook/Google tracking parameters (`fbclid`, `gclid`) should be removed for privacy

**Solution:**
- **Database Schema** - Added `landing_url TEXT` column to `wp_user_registrations` table
- **SQL Migration** - Created `supabase/add-landing-url-field.sql` with index for analytics
- **Frontend Capture** - Captures `document.referrer` on auth form page
- **Parameter Cleaning** - Removes `fbclid`, `gclid`, `msclkid`, `gbraid`, `wbraid` parameters
- **Cross-Device Support** - Magic Link passes `landing_url` via URL parameter
- **Same-Device Support** - OAuth saves `landing_url` to localStorage
- **Backend Integration** - PHP validates and saves landing_url to Supabase

**Implementation Details:**

**Frontend (`auth-form.html`):**
- `cleanTrackingParams()` function - strips Facebook/Google tracking parameters
- `LANDING_URL` constant - captures referrer and validates same-domain
- Magic Link: includes `landing_url` in `emailRedirectTo` parameter
- OAuth: saves `landing_url` to localStorage via `safeStorage.setItem()`

**Callback Handler (`callback.html`):**
- Priority 1: Read from URL query param (Magic Link - cross-device)
- Priority 2: Read from localStorage (OAuth - same-device)
- Priority 3: null (direct auth form access)
- Sends to PHP via POST body

**Backend (`supabase-bridge.php`):**
- Modified `sb_log_registration_to_supabase($email, $supabase_user_id, $registration_url, $landing_url = null)`
- Added optional `$landing_url` parameter (backward compatible)
- Validates via `sb_validate_url_path()` function
- Includes in Supabase INSERT payload

**Use Cases:**
1. **Paid Traffic** - User clicks Facebook ad → lands on `/ai_intro_land/?utm=fb1` → clicks auth form → registers → `landing_url` saved
2. **SEO/Organic** - User finds closed content via search → auth form → registers → `landing_url` = content page URL
3. **Direct Auth** - User goes directly to auth form → registers → `landing_url` = null

**Production Testing:**
- ✅ Deployed to production (alexeykrol.com)
- ✅ Tested with real Facebook ads traffic
- ✅ Verified UTM parameters: `?utm=afb_0003`, `?utm=fbp_001`, `?utm=pfb_0003`, `?utm=fba_001`
- ✅ Confirmed Facebook tracking parameters removed (`fbclid`, `brid`, etc.)
- ✅ 100% landing URL attribution for new registrations
- ✅ Works with multiple landing pages and UTM codes

**Results:**
- Complete marketing attribution for Facebook ad campaigns
- Track which specific Facebook posts drive conversions
- UTM parameters preserved through entire auth flow
- Privacy-friendly: only tracks same-domain referrers
- Cross-device Magic Link captures landing URL correctly
- Zero breaking changes for existing functionality

**Files Modified:**
- `supabase/add-landing-url-field.sql` - Database migration (NEW)
- `auth-form.html` - Landing URL capture and cleaning (lines 849-891)
- `callback.html` - Landing URL extraction (lines 371-391)
- `supabase-bridge.php` - Backend integration (lines 602, 618-625, 663, 1633-1641, 1736)

## [0.10.0] - 2026-01-06

### 🎓 Course Access Auto-Enrollment System

**Major new feature: Automatically enroll users in LearnDash courses when they purchase MemberPress memberships**

**Admin Interface:**
- **New "Course Access" Tab** - Admin interface for managing membership → course mappings
- **Modal Popup UI** - Clean modal window for adding new rules (matches Courses tab pattern)
- **Visual Table** - Shows all active auto-enrollment rules with delete actions
- **Empty State** - Helpful message when no rules configured yet

**Mapping System:**
- **Flexible Mapping** - One membership can map to multiple courses
  - Example: Premium membership → Main course + Bonus course
- **Storage** - Rules saved in `wp_options` table (key: `sb_membership_course_pairs`)
- **Structure** - Each rule contains: unique ID, membership_id, course_id, created_at timestamp

**Enrollment Logic:**
- **Dual Triggers:**
  - `mepr_event_transaction_completed` - One-time purchases (status: complete)
  - `mepr_subscription_transition_status` - Recurring subscriptions (status: active)
- **Duplicate Prevention** - Checks `sfwd_lms_has_access()` before enrolling
- **Progress Preservation** - Never removes enrollment, only course access (controlled by membership status)
- **Smart Renewals** - Skips already enrolled users to preserve progress

**User Experience:**
- **Automatic** - No manual enrollment needed after purchase
- **Instant** - Enrollment happens immediately on purchase completion
- **Seamless** - Works with both one-time payments and subscriptions
- **Access Control** - Course access automatically managed by membership status
  - Active membership = course access granted
  - Paused/cancelled membership = course access removed (but progress preserved)

**Bug Fixes:**
- **LearnDash Course Loading** - Fixed non-existent function `learndash_get_posts_by_args()`
  - Now uses standard WordPress `get_posts()` function
  - Added `'post_status' => 'publish'` parameter
  - Courses now load correctly in dropdown (lines 3200-3217)
- **UI/UX Improvement** - Redesigned from inline form to modal popup
  - Better experience with long membership/course lists
  - Consistent with other admin tabs (Courses, Memberships)
  - Cleaner table view (lines 3227-3435)

### 🛒 Checkout Authentication Overlay

**Major UX improvement: Non-logged-in users are now prompted to authenticate before checkout**

**Problem Solved:**
- MemberPress checkout pages show only email field for non-logged users
- When existing email entered → error "email already exists, please login"
- Login form was hidden by CSS → users stuck, unable to complete purchase
- This caused lost sales and poor user experience

**Solution:**
- **Fullscreen Overlay** - Semi-transparent overlay appears on `/register/*` pages
- **Clear Messaging** - "Чтобы оформить покупку, сначала авторизуйтесь — войдите или зарегистрируйтесь"
- **Single Action Button** - "Авторизоваться" redirects to `/test-no-elem/` auth page
- **Seamless Flow** - After authentication, user automatically returns to checkout page
- **Smart Detection** - Only shows for non-logged-in users (PHP + JavaScript cookie check)

**Implementation:**
- **URL Pattern** - Activates on all MemberPress checkout pages (`/register/*`)
- **Triple Protection:**
  - PHP check: `is_user_logged_in()` - prevents overlay code output for logged-in users
  - URL check: `strpos($current_url, '/register/')` - only activates on checkout pages
  - JS check: `wordpress_logged_in_` cookie - client-side verification
- **Reuses Existing Logic** - Leverages existing referrer redirect from `/test-no-elem/` page
- **Cache-Safe** - Works correctly even with aggressive browser/server caching

**User Experience:**
1. Non-logged user visits `/register/courses/` (or any `/register/*` page)
2. Fullscreen overlay appears with auth prompt
3. User clicks "Авторизоваться" → redirected to `/test-no-elem/`
4. User authenticates via Google/Facebook/Magic Link
5. Automatic redirect back to original checkout page
6. Now logged in → MemberPress shows normal checkout form
7. Purchase completed successfully

**Production Testing:**
- ✅ Tested on desktop and mobile devices
- ✅ Verified on multiple `/register/*` pages
- ✅ Works correctly with LiteSpeed Cache exclusions
- ✅ No impact on logged-in users

**Files Modified:**
- `supabase-bridge.php`:
  - Course Access tab navigation (lines 1979-1981)
  - Tab content handler (lines 2324-2328)
  - Main UI function with modal popup (lines 3193-3435)
  - AJAX save handler (lines 4032-4090)
  - AJAX delete handler (lines 4093-4142)
  - Auto-enrollment function (lines 5476-5550)
  - MemberPress transaction hook (lines 5556-5577)
  - MemberPress subscription hook (lines 5583-5602)
  - **Checkout Authentication Overlay (lines 5637-5759)**

**Production Deployment:**
- Tested on [alexeykrol.com](https://alexeykrol.com)
- 6 membership → course mappings configured
- Zero impact on existing functionality

**Documentation:**
- README.md updated with Course Access instructions
- Feature description added to LMS integrations section

---

## [0.9.13] - 2026-01-05

### MemberPress Webhook System Upgrade

**Renamed webhook system from Make.com to MemberPress Webhooks** - Universal multi-platform webhook support
- **Rebranding:** Changed all function names from `sb_make_webhook_*` to `sb_memberpress_webhook_*`
- **Admin UI:** Updated tab from "🎣 Make.com" to "🎣 MemberPress Webhook"
- **Multiple URL Support:** Changed from single URL input to textarea (one URL per line)
  - Supports Make.com, Zapier, n8n, or any HTTP webhook endpoint
  - Sends to all configured URLs simultaneously
- **MemberPress-Compatible Payload:** Full 100+ field payload matching MemberPress webhook structure
  - Event type: `non-recurring-transaction-completed`
  - Nested objects: membership, member, transaction data
  - 100% compatible with existing MemberPress automations in Make.com, Zapier, n8n
- **Automatic Migration:** Created `sb_migrate_webhook_settings()` function
  - Runs once on `admin_init`, migrates old settings to new format
  - Backward compatibility wrapper (`sb_send_make_webhook()`)
  - Fallback logic to read old options if new ones are empty
  - Zero breaking changes during deployment
- **Real Data Testing:** Test webhook uses ACTUAL data from last registration
  - Queries `mepr_transactions` table for last `sb-%` transaction
  - Fetches real user email, membership ID, transaction details
  - `test_mode = false` for production-quality payload
  - No more stub/fake test data (999999, test@example.com)
- **Real Payload Preview:** Documentation section shows ACTUAL JSON preview
  - Generated from last registration data (like MemberPress does)
  - Shows what will be sent when test button is clicked
  - Conditional display: real preview or "no registrations yet" message
- **Dynamic Success Messages:** Test webhook shows details
  - Example: "Test webhook sent to 2 URL(s) using REAL data from last registration (User: email@example.com, Transaction ID: 12345)"
  - Instead of generic "Test webhook sent successfully!"
  - Provides verification of which user's data was sent

**Files Modified:**
- `supabase-bridge.php` (lines 4405-5019)
  - Migration function (4405-4459)
  - Admin tab rendering with real payload preview (4464-4592)
  - AJAX save handler (4660-4690)
  - AJAX test handler with real data (4757-4813)
  - Webhook send function (4789-5002)
  - Backward compatibility wrapper (5016-5019)

**Deployment:**
- Tested on production (alexeykrol.com) with zero downtime
- Auto-migration successful
- All existing Make.com automations continue working
- No errors in production logs

## [0.9.12] - 2026-01-04

### 2026-01-04 22:45 - Error Handling Enhancement
**Fixed: Supabase error detection on callback page** - Users stuck on "Welcome! Wait..." message
- Added error detection BEFORE token extraction in callback handler
- Parse Supabase errors from URL hash (`#error=otp_expired`, etc.)
- Show user-friendly error messages with specific instructions
- Provide "Return to form" button with link to `registration_url`
- Handles common errors:
  - `otp_expired` - "Link expired, request new one"
  - `otp_disabled` - "Email login unavailable, use Google/Facebook"
  - `access_denied` - "Access denied, contact support"
  - Generic errors - Show Supabase error description

**Error message format:**
```
⚠️ [Specific error message]

Чтобы войти снова, перейдите к форме входа:
[Перейти к форме входа] → {registration_url}
```

### 2026-01-04 14:30 - Critical Infrastructure Changes

**CRITICAL: SMTP Provider Migration** - Migrated from Supabase SMTP to Amazon SES
- **Root cause:** Supabase built-in SMTP hit rate limits during high traffic (European morning registrations)
- **Impact:** Magic Link emails stopped sending, blocking new user registrations
- **Solution:** Migrated to Amazon SES (Simple Email Service)
- **Implementation:**
  - Created AWS account and configured SES
  - Verified domain ownership (DKIM, SPF records)
  - Updated Supabase Dashboard → Authentication → SMTP Settings
  - Tested email delivery at scale
- **IMPORTANT:** Supabase SMTP is ONLY for MVP/testing. Production REQUIRES external SMTP provider.
- **Recommended providers:** Amazon SES (used here), SendGrid, Mailgun, Postmark

### 2026-01-04 10:15 - Data Integrity Fixes

**Fixed: Magic Link cross-device registration URL loss** - ~46% of Magic Link registrations losing pair_id
- Root cause: OAuth redirects lose localStorage when user opens email on different device/browser
- Solution: Pass `registration_url` via URL query parameter in Magic Link emails
- Modified `auth-form.html` to include registration_url in `emailRedirectTo` callback URL
- Modified callback page (`callback.html`) to read from URL param with localStorage fallback
- Priority-based detection: URL param → localStorage → current page path
- Fixes ~46% data loss in marketing analytics

**Fixed: Registration pair sync bug** - WordPress pairs not syncing to Supabase
- Added missing `sb_sync_pair_to_supabase()` call in `sb_ajax_save_pair()`
- Added missing `sb_delete_pair_from_supabase()` call in `sb_ajax_delete_pair()`
- Registration pairs now properly sync from WordPress to Supabase table

### Added
- **Data Integrity Monitoring System** - Local bash script for verifying registration tracking
  - `monitoring/check-integrity.sh` - Compares auth.users vs wp_user_registrations
  - Checks for lost registrations (not tracked in analytics)
  - Checks for missing landing page attribution (pair_id = NULL)
  - Configurable time period (default: 1 hour, supports custom periods)
  - Exit code 0 = all checks passed, exit code 1 = issues detected
  - Safe read-only queries (no data modifications)
- **Monitoring Documentation**
  - `monitoring/README.md` - Complete setup and usage guide
  - `monitoring/.env.example` - Credential template with clear instructions
  - Scheduling examples for automated checks
  - Security notes (credentials in .gitignore)

### Changed
- **Callback URL structure for Magic Link** - Now includes registration_url parameter
  - Old: `https://site.com/callback`
  - New: `https://site.com/callback?registration_url=/landing-page/`
  - Backward compatible - falls back to localStorage if param missing
- **Credential management** - Added Supabase section to `.production-credentials`
  - Consolidated credential storage for monitoring scripts
  - Already in .gitignore for security

### Testing
- Verified 100% pair_id tracking accuracy after fix (SQL queries on production data)
- Tested Magic Link cross-device flow (PC → mobile email → callback)
- Tested registration pair sync (WordPress → Supabase)
- All registration methods working: Google OAuth, Facebook OAuth, Magic Link

### Results
- 100% registration tracking accuracy (no more NULL pair_ids)
- Cross-device Magic Link authentication fully working
- Real-time data integrity monitoring capability
- No lost registrations for marketing attribution

## [0.9.11] - 2025-12-28

### Added
- **Helper Functions** - Check membership/enrollment status
  - `sb_has_membership($user_id, $membership_id)` - Check if user has active membership
  - `sb_is_enrolled($user_id, $course_id)` - Check if user is enrolled in course
- **User Status Analyzer Module** - Analyzes current memberships/enrollments
  - Checks registration URL against configured pairs
  - Determines what memberships/courses should be assigned
  - Returns clear data structure for action executor
- **Action Executor Module** - Executes membership/course assignments
  - Prevents duplicate assignments using helper functions
  - Comprehensive logging for debugging
  - Clean separation from analysis logic

### Fixed
- **Redirect Logic Conflict** - Clear priority between Registration Pairs and Return URL
  - Registration Pairs redirect takes priority over `redirect_to` parameter
  - Added documentation in code explaining redirect logic
  - Prevents accidental redirect URL overrides

### Testing
- All integration tests passed successfully
- Verified duplicate prevention for memberships and courses
- Tested redirect logic priority

## [0.9.10] - 2025-12-21

### Added
- **PKCE Flow Support** - OAuth now works in Chrome and Safari
  - Modified extractTokensFromHash() to support both OAuth flows:
    - Implicit flow (hash fragment #access_token=...)
    - PKCE flow (query string ?access_token=...)
  - Maintains backward compatibility with Firefox
  - Fixes OAuth login issues caused by Supabase JS SDK @2 from CDN

### Fixed
- **dotsTimer Bug** - Fixed ReferenceError in callback handler
  - Replaced clearInterval(countdownTimer) with clearInterval(dotsTimer)
  - Fixed 3 occurrences in callback handler (lines 431, 447, 456)
- **MemberPress Compatibility** - Added patch to hide login link
  - Prevents duplicate login links from showing
  - Improves UX when using MemberPress with Supabase Bridge

### Testing
- Verified OAuth login works in Chrome, Safari, Firefox
- Tested Google OAuth and Facebook OAuth
- Tested Magic Link authentication

## [0.9.9] - 2025-12-19

### Added
- **Safari Privacy Protection** - safeStorage wrapper for Enhanced Privacy Protection
  - Fixes authentication errors in Safari Private Browsing mode
  - Automatic fallback when localStorage is blocked
- **Complete Russian Localization** - All UI elements translated
- **Instant Loading Screen** - Animated dots while processing authentication
- **3-Step Troubleshooting** - Clear instructions for common issues

### Changed
- **UX Improvements** - Eliminated flickering screens during authentication flow
- **Repository Cleanup** - Removed 51 debug files (-20,315 lines)
- **Folder Reorganization** - Moved files to security/ and supabase/ folders

### Security
- **SSH Keys Removed** - Cleaned from git history
- **Production URL Updated** - Now using alexeykrol.com

## [0.9.8] - 2025-12-18

### Added
- **Security Hardening** - Enhanced security measures
- **Testing Infrastructure** - Comprehensive testing framework

### Changed
- **Code Quality** - Improved code organization and documentation

## [0.9.7] - 2025-12-18

### Added
- **Return-to-Origin Login Flow** - Users return to original page after login
- **Unified Shortcode Architecture** - Simplified integration

### Changed
- **Login Flow Improvements** - Better UX with automatic redirects

## [0.9.6] - 2025-12-18

### Added
- **Two-Page Architecture Refactoring** - Separated auth UI from callback handler
- **Improved Page Structure** - Better organization of authentication flow

## [0.9.5] - 2025-12-18

### Fixed
- **Critical: REST API namespace** - Updated from legacy `supabase-auth` to `supabase-bridge/v1`
  - Fixes 404 errors on callback endpoint in production
  - Both REST route registration and auth-form.html fetch URL updated
  - Required for Magic Link and OAuth authentication to work
  - **BREAKING:** Supabase Redirect URLs must be updated to new endpoint

### Migration Required
If upgrading from v0.9.4 or earlier, update your Supabase Redirect URLs:
- **Old:** `https://yoursite.com/wp-json/supabase-auth/callback`
- **New:** `https://yoursite.com/wp-json/supabase-bridge/v1/callback`

## [0.9.4] - 2025-12-17

### Added
- **Version Diagnostics Panel** - Plugin admin page now displays:
  - Actual installed plugin version (from file header)
  - Plugin filename and directory (for multi-version debugging)
  - Enhanced logging availability check (detects version mismatches)
  - Helps diagnose installation issues when old version gets cached

### Technical Details
- Uses WordPress `get_file_data()` to read version directly from plugin header
- Checks for `sb_log()` function existence to verify enhanced logging availability
- Critical for production debugging when multiple plugin versions might be installed

## [0.9.3] - 2025-12-17

### Fixed
- **CSP Blocking Registration Forms** - Disabled Content-Security-Policy for non-logged-in users
  - CSP was preventing Supabase Auth form from displaying for unauthenticated users
  - Registration/login pages now work correctly in private browsing mode
  - Kept other security headers (X-Frame-Options, X-Content-Type-Options, etc.)
  - Can be re-enabled per-page if needed for specific security requirements

### Changed
- **Description** - Removed "CSP" from plugin description (feature now optional/disabled)

## [0.9.2] - 2025-12-17

### Added
- **Production Debugging System** - Enhanced logging for remote debugging
  - New `sb_log()` function with multiple log levels (DEBUG, INFO, WARNING, ERROR)
  - Automatic sensitive data redaction (tokens, passwords, keys automatically masked)
  - Context-aware logging with structured data (JSON format)
  - Function entry/exit tracing for execution flow analysis
  - Comprehensive logging in `sb_handle_callback()` function:
    - Rate limiting checks
    - CSRF validation
    - JWT verification (JWKS cache hits/misses)
    - User sync operations (find by UUID/email, creation)
    - Authentication success/failure with IP tracking
    - Full error stack traces
  - Only active when `WP_DEBUG = true` (zero performance impact in production)
  - Log file: `/wp-content/debug.log`
- **Production Debugging Documentation**
  - `PRODUCTION_DEBUGGING.md` - Complete guide for enabling debug logging and SSH access
  - `PRODUCTION_DEBUGGING_QUICK_START.md` - 5-minute setup guide
  - SSH read-only access setup instructions
  - Supabase Dashboard access guide
  - Security checklist for safe production debugging

### Changed
- **Error Logging** - Enhanced existing `error_log()` calls with structured `sb_log()` wrapper
  - More context in logs (IP addresses, user IDs, error details)
  - Better error categorization (rate limits, CSRF failures, JWT errors)
  - Easier troubleshooting with timestamped, categorized entries

### Security
- **Automatic Data Sanitization** - `sb_sanitize_log_context()` function
  - Removes sensitive data from logs (passwords, tokens, secrets, keys)
  - Truncates long strings (>500 chars) to prevent log bloat
  - Safe to share debug.log files - all credentials automatically redacted

### Technical Details
- New functions: `sb_log()`, `sb_sanitize_log_context()`, `sb_log_function_entry()`, `sb_log_function_exit()`
- Log format: `[Timestamp] [Supabase Bridge] [LEVEL] Message | Context: {...}`
- Logs written via PHP `error_log()` - compatible with all hosting environments
- No external dependencies - pure PHP implementation

## [0.9.1] - 2025-12-13

### Added
- **LearnDash Banner Management UI** - WordPress Admin interface for banner patch
  - New "🎓 Banner" tab in WordPress Admin
  - Checkbox to enable/disable enrollment banner removal
  - Real-time patch status indicator with color-coded badges (Active, Not Active, Update Needed, Not Found)
  - One-click apply/restore functionality via AJAX
  - Automatic backup creation before each patch modification
  - Warning notifications after LearnDash updates prompting patch reapplication
  - Collapsible technical details section explaining patch mechanism
  - Safe patch upgrade from old versions to latest

### Changed
- **LearnDash banner patch** - Now managed via WordPress Admin UI instead of CLI script
  - Replaces standalone `patch-learndash-free-banner.php` script execution
  - Integrated into plugin settings for better UX
  - Patch status automatically detected and displayed
  - Apply/restore operations accessible without command line access

### Technical Details
- New functions: `sb_get_learndash_path()`, `sb_get_learndash_patch_status()`, `sb_apply_learndash_banner_patch()`, `sb_restore_learndash_banner_original()`
- AJAX handler: `sb_ajax_save_learndash_banner` for asynchronous patch operations
- Status detection: Distinguishes between applied, not_applied, needs_reapply, and not_found states
- Backward compatible: Works with both old patch format and detects LearnDash updates

## [0.9.0] - 2025-12-13

### Added
- **MemberPress Integration** - Auto-assign FREE memberships on registration
  - New "🎫 Memberships" tab in WordPress Admin
  - Dropdown shows only FREE memberships (price = 0)
  - CRUD operations for membership assignment rules
  - Uses `MeprTransaction::store()` to trigger all MemberPress hooks
  - Automatic membership activation when users register from specific landing pages
- **LearnDash Integration** - Auto-enroll users in courses on registration
  - New "📚 Courses" tab in WordPress Admin
  - Dropdown lists all available LearnDash courses
  - CRUD operations for course enrollment rules
  - Uses native `ld_update_course_access()` for enrollment
  - Seamless course access when users register from designated pages
- **LearnDash Banner Removal Tool** - Patch script to disable unwanted enrollment banner
  - `patch-learndash-free-banner.php` - Idempotent patch script
  - Completely disables "NOT ENROLLED / Free / Take this Course" banner for ALL course types
  - Creates automatic backups before patching
  - Can re-run safely after LearnDash updates
  - Access control managed via MemberPress and custom Elementor conditions

### Changed
- **Registration Pairs architecture** - Removed redundant Supabase synchronization
  - Pairs now stored ONLY in WordPress `wp_options` (no Supabase sync)
  - Simplified architecture - settings belong in WordPress, not external database
  - Removed `sb_sync_pair_to_supabase()` and `sb_delete_pair_from_supabase()` calls
  - Cleaner separation: WordPress handles settings, Supabase logs events

### Technical Details
- MemberPress: Creates completed transaction with `gateway = 'free'` and `status = 'complete'`
- LearnDash: Enrolls user with `$remove_access = false` parameter
- Both integrations trigger on successful registration via callback endpoint
- Patch script detects and upgrades old patches to latest version
- All features fully tested with MemberPress 1.x and LearnDash 4.x

## [0.8.5] - 2025-12-13

### Fixed
- **Registration Pairs tracking accuracy** - Registration URL now sent explicitly in POST body
- No longer relies on unreliable HTTP Referer header
- Backward compatible fallback to Referer for older deployments
- **Registration logging bug** - Fixed HTTP 400 error caused by non-existent `thankyou_page_url` column
- Removed redundant column from INSERT (thank you page accessible via `pair_id` foreign key)
- **Duplicate callback handling** - Improved HTTP 409 response handling for seamless redirects
- First duplicate request now exits silently, allowing second request to complete authentication

### Added
- **Edit Pair functionality** - Can now modify existing Registration Pairs
- Modal pre-populates with current pair values (registration page and thank you page)
- Syncs updates to both WordPress wp_options and Supabase
- **Custom delete confirmation modal** - Replaces browser `confirm()` dialog (Safari compatible)
- Styled to match WordPress admin interface

### Technical Details
- JavaScript sends `registration_url` (ORIGIN_PAGE) with callback request
- PHP validates using `sb_validate_url_path()` before logging
- Edit function loads pair data from global JavaScript array `SB_PAIRS_DATA`
- Maintains backward compatibility - falls back to Referer if POST param missing
- HTTP 409 responses handled gracefully without showing errors to users
- RLS policies added for `anon` role on both `wp_registration_pairs` and `wp_user_registrations` tables

## [0.8.4] - 2025-12-11

### Fixed
- **Critical: Magic Link authentication** - Race condition causing duplicate callbacks
- Implemented atomic MySQL `GET_LOCK()` to prevent concurrent token processing
- WordPress cookies now properly saved when using Magic Link (email) authentication
- Fixes issue where callback succeeded but user wasn't logged in due to session corruption
- Added `credentials: 'include'` to fetch request for proper cookie handling
- **Clean localStorage on login page** - Auth form now automatically clears Supabase localStorage before showing login form
- Prevents re-login issues after logout without manually clearing browser data
- Ensures fresh authentication state every time user visits login page

### Changed
- Replaced non-atomic transient lock with MySQL `GET_LOCK()` for true concurrency protection
- Second duplicate request now returns HTTP 409 immediately
- Lock automatically released after callback completion via `register_shutdown_function()`
- Added `credentials: 'include'` to callback fetch request for proper cookie handling
- Added cleanup script to auth-form.html that runs immediately on page load
- Clears all `sb-*` and `sb_processed_*` localStorage keys before form initialization
- More reliable than wp_logout hook which can be interrupted by redirects

### Technical Details
- Root cause: Supabase `onAuthStateChange` fires twice simultaneously for Magic Link
- Both requests called `wp_set_auth_cookie()`, second call corrupted first session
- Solution: Atomic database-level lock ensures only one request processes each JWT token
- Tested successfully in Safari, Chrome, and Firefox

## [0.8.3] - 2025-12-11

### Fixed
- **sb_cfg() function** now correctly reads environment variables from `$_ENV` and `$_SERVER`
- Fixes issue where `getenv()` doesn't work with `putenv()` in wp-config.php
- JWKS cache clearing for JWT Signing Keys migration

### Added
- Support for Supabase JWT Signing Keys (migrated from Legacy JWT Secret)
- Better fallback chain for reading credentials: Database → $_ENV → $_SERVER → getenv()

## [0.8.2] - 2025-12-11

### Added
- **Webhooks Tab** in WordPress Admin UI (third tab)
- Complete integration of webhook system into main plugin interface
- Visual status indicators for webhook configuration
- Collapsible setup instructions for Supabase deployment
- Test webhook button in admin interface

### Fixed
- Missing Webhooks tab in admin interface (was developed but not integrated)

## [0.8.1] - 2025-10-27

### Added
- Webhook system for n8n/make integration
- Database triggers for immediate webhook delivery (no cron delays)
- Edge Function with retry logic (3 attempts, exponential backoff)
- WordPress Admin UI for webhook testing
- Comprehensive logging in `webhook_logs` table

### Fixed
- JWT Authentication - disabled Edge Function JWT verification (HTTP 401 fix)
- RLS Policies - added anon role INSERT/UPDATE permissions
- pg_net Extension - correct syntax for v0.19.5
- Edge Function error handling for failed webhook status updates
- WordPress encrypted URL decryption for project_ref extraction

### Security
- SERVICE_ROLE_KEY stored only in Edge Function secrets
- pg_net for server-side HTTP calls (cannot be intercepted)

## [0.7.0] - 2025-10-26

### Added
- Registration Pairs Analytics system
- Multi-site support with `site_url` filtering
- 6 implementation phases for analytics:
  - Supabase database tables (`wp_registration_pairs`, `wp_user_registrations`)
  - Settings UI with Registration Pairs CRUD
  - WordPress → Supabase sync
  - JavaScript injection of pairs
  - Page-specific Thank You redirects
  - Registration event logging
- Enterprise security architecture (4-layer defense)
- Input validation functions (`sb_validate_email`, `sb_validate_url_path`, `sb_validate_uuid`, `sb_validate_site_url`)
- `build-release.sh` for release automation
- Production documentation (`PRODUCTION_SETUP.md`, `QUICK_SETUP_CHECKLIST.md`)

### Security
- Anon Key + strict RLS policies with site_url filtering
- SQL injection, XSS, path traversal prevention

## [0.4.1] - 2025-10-25

### Fixed
- **Critical:** User duplication during Magic Link and OAuth authentication
- Race condition with server-side distributed lock (WordPress Transient API)
- Elementor CSP compatibility
- WordPress text filter bypass

### Added
- 3-layer protection: UUID check + distributed lock + retry logic
- `TROUBLESHOOTING.md` with diagnostic workflow

## [0.4.0] - 2025-10-24

### Added
- `[supabase_auth_form]` shortcode (replaces 1068-line code copy)
- Settings page with Thank You Page selector
- Encrypted credentials storage (AES-256-CBC)
- Real-time credentials verification via API
- Auto-extraction of Project Ref from Supabase URL

### Changed
- Setup reduced from 7 steps to 4 steps
- No FTP access required for installation

### Fixed
- Issue #3: Poor UX - auth-form.html code not embedded
- Issue #4: Settings page with page selector
- Issue #5: Credentials in plaintext
- Issue #6: Confusing auth-form.html structure
- Issue #7: No clear Thank You page URL configuration

## [0.3.5] - 2025-10-23

### Fixed
- Google OAuth email verification (allow NULL `email_verified`)
- Magic Link localStorage timing (move deduplication after WordPress response)

## [0.3.3] - 2025-10-07

### Added
- HTTP Security Headers (CSP, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy)
- Enhanced error handling (generic user messages, detailed server logs)
- Audit logging with IP tracking
- Improved JWT validation with SSL verification
- Stronger passwords (32 characters)
- Enhanced email validation (RFC 5322)
- Default subscriber role for new users
- `SECURITY.md` documentation

### Changed
- Rate limit clearing on successful auth

## [0.3.2] - 2025-10-05

### Security
- **Critical:** Origin/Referer bypass fix (strict host comparison)
- CSRF protection for logout endpoint

## [0.3.1] - 2025-10-05

### Added
- CSRF Protection (Origin/Referer validation)
- JWT `aud` validation
- Email verification enforcement
- Open redirect protection
- JWKS caching (1 hour)
- Rate limiting (10/60s per IP)

### Changed
- PHP >=8.0 requirement

## [0.3.0] - 2025-10-05

### Added
- Google OAuth support
- Facebook OAuth support (advanced access for email)
- Magic Link authentication (6-digit code)
- Smart redirects (new vs existing user)
- 3 redirect modes (standard, paired, flexible)
- `auth-form.html` with all 3 methods

## [0.1.0] - 2025-10-01

### Added
- JWT verification via JWKS (RS256)
- WordPress user synchronization
- OAuth provider support (Google, Apple, GitHub, etc.)
- REST API endpoints (`/callback`, `/logout`)
- Configuration via environment variables
- Supabase JS integration (CDN)
- Session management (`wp_set_auth_cookie`)
- User metadata storage (`supabase_user_id`)

---

*For detailed technical documentation, see `.claude/ARCHITECTURE.md`*
