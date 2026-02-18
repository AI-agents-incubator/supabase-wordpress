# AI Testing Framework: Deep End-to-End Testing

## What This Is

This document is an operational guide for AI agents conducting deep user-facing testing of web applications. It defines roles, access requirements, and methodology for autonomous testing that covers the full development cycle: code changes, deployment, user flow testing, diagnostics, and regression prevention.

The framework combines three capabilities:

1. **Visual Browser Agent** (Cowork) — sees and interacts with the site as a real user
2. **CLI Agent** (Claude Code) — has full system access, writes code, deploys to production
3. **Playwright** — programmatic browser automation for reproducible tests and HTTP-level inspection

No single tool covers everything. Their combination creates a complete testing loop.

---

## Why This Matters

Without this framework, bugs are found only when real users hit them. With it, an AI agent can:

- Simulate every user scenario before users encounter it
- Catch broken auth flows, missing redirects, lost cookies, cache issues
- Test edge cases that are hard to script (double-clicks, back-button during OAuth, VPN interruptions)
- Inspect HTTP-level details invisible in a browser (Set-Cookie headers, CORS, redirect chains)
- Deploy fixes and verify them in a single autonomous cycle

---

## Roles and Capabilities

### Visual Browser Agent (Cowork)

**Access:** Browser tabs via Chrome MCP extension, project files (read), Playwright execution.

**Strengths:**
- Sees the page as a user sees it — layout, buttons, text, errors
- Semantic understanding: knows that "Продолжить через Google" is an OAuth button without needing a CSS selector
- Adapts to UI changes on the fly — no brittle selectors
- Can read browser console, network requests, execute JavaScript
- Takes screenshots at every step for visual verification
- Improvises: tries unexpected actions a real user might take

**Limitations:**
- Loses JavaScript context on page navigation (console, injected scripts reset)
- Cannot see HTTP response headers (Set-Cookie, CORS) — browser hides them from JavaScript
- Cannot deploy files to production servers (no SSH access by default)
- Works in isolated VM — sees only the mounted project folder

**Best for:** Exploratory testing, edge cases, visual verification, user flow walkthroughs, real-time monitoring.

### CLI Agent (Claude Code)

**Access:** Full filesystem, SSH to production, git, npm, all CLI tools.

**Strengths:**
- Reads and writes any file on the developer's machine
- SSH access to production servers — can deploy, read logs, restart services
- Full git workflow — commits, branches, PRs
- Can install and configure tools
- Runs Playwright scripts with full HTTP-level access

**Limitations:**
- Cannot see rendered web pages visually
- Cannot click buttons or fill forms in a browser
- Cannot verify visual layout or user-facing text
- Playwright scripts must be pre-written — cannot improvise during page interaction

**Best for:** Code changes, deployment, server-side diagnostics (PHP logs, error logs), writing and running Playwright tests, git operations.

### Playwright

**Access:** Programmatic browser — full HTTP interception, cookies, headers, multi-page navigation.

**Strengths:**
- Intercepts ALL HTTP headers including Set-Cookie (invisible to browser JavaScript)
- Maintains context across page navigations (critical for OAuth flows)
- Can monitor redirect chains without losing data
- Reproducible: same script, same result every time
- Runs headless — fast, no UI needed
- Can simulate network conditions, block requests, inject responses

**Limitations:**
- Requires pre-written scripts — cannot improvise
- Selector-based: breaks when UI changes (unless using AI-assisted selectors)
- Cannot "understand" what's on the page semantically
- Cannot make judgment calls about whether something "looks right"

**Best for:** Regression tests, HTTP-level diagnostics, OAuth flow inspection, cookie verification, CI/CD integration.

---

## Access Requirements

For the full autonomous testing cycle, agents need:

### Minimum Access (Any Agent)

| Resource | Purpose |
|----------|---------|
| Project source code (read) | Understanding codebase, finding bugs |
| Browser access OR Playwright | Testing user flows |
| Production site URL | Target for testing |

### Full Autonomous Cycle (CLI Agent)

| Resource | Purpose |
|----------|---------|
| Project source code (read/write) | Code changes, fixes |
| SSH credentials to production | Deploy files, read server logs |
| Supabase/Firebase/Auth credentials | Generate test tokens, inspect auth state |
| Git access | Commits, branches |
| Playwright installed | Automated testing |
| PHP/application error logs path | Server-side diagnostics |

### Full Autonomous Cycle (Visual Agent + CLI collaboration)

| Resource | Purpose |
|----------|---------|
| Browser tabs (Visual Agent) | User flow testing, visual verification |
| SSH + filesystem (CLI Agent) | Deploy, logs, code changes |
| Playwright (either agent) | HTTP-level inspection |
| Shared project folder | Both agents see the same codebase |

### Credential Storage Convention

Store production credentials in `.production-credentials` in the project root (must be in `.gitignore`). Format:

```
SSH_HOST=<server_ip>
SSH_USER=<username>
SSH_PORT=<port>
WP_PATH=<wordpress_path_on_server>
SUPABASE_URL=<supabase_project_url>
SUPABASE_ANON_KEY=<anon_key>
SUPABASE_SERVICE_ROLE_KEY=<service_role_key>
```

The CLI agent reads this file and uses credentials silently in commands. Never output credentials in dialog.

---

## Testing Methodology

### Level 1: Smoke Test (2 minutes)

Quick verification that critical paths work.

**Who:** Visual Browser Agent or Playwright.

**Steps:**
1. Open the main page — does it load?
2. Navigate to login — does the form render?
3. Click each auth button — do they redirect to providers?
4. Check console for JavaScript errors
5. Verify API endpoints respond (fetch /wp-json/ or equivalent)

**Pass criteria:** No 500 errors, no JS exceptions, all buttons clickable, API responds.

### Level 2: Happy Path Testing (10 minutes)

Full user scenarios that should work perfectly.

**Who:** Visual Browser Agent (interactive) + Playwright (HTTP inspection).

**Scenarios to cover:**
- New user registration via each auth method (Google, Facebook, Magic Link, email/password)
- Existing user login via each method
- Logout and re-login
- Password reset flow
- Redirect after login (from landing page, from general login, direct URL)
- Session persistence (login, close tab, reopen — still logged in?)

**For each scenario, verify:**
- Visual: correct pages shown, no error messages, proper redirects
- HTTP: Set-Cookie headers present, correct domain/path/SameSite attributes
- Cookies: WordPress auth cookies exist after login
- Server: no PHP errors in logs during the flow

### Level 3: Edge Case Testing (20 minutes)

Scenarios where users deviate from the happy path.

**Who:** Visual Browser Agent (primary — can improvise), Playwright (for specific HTTP checks).

**Scenarios:**

**Authentication edge cases:**
- Double-click on OAuth button
- Click OAuth, press browser Back during provider redirect
- Click Magic Link in a different browser/device than where it was requested
- Click expired Magic Link (wait > link TTL)
- Enter invalid email format, then valid email
- Submit empty form
- Rapid sequential login attempts (rate limiting)
- Login with VPN enabled/disabled mid-flow
- Login in incognito/private mode
- Login with cookies disabled

**Session edge cases:**
- Open login in two tabs, complete auth in one — what happens in the other?
- Login, then navigate directly to callback URL with old/invalid tokens
- Login, clear cookies, refresh page
- Login on mobile viewport (responsive layout)

**Cache edge cases:**
- Login, check if cached pages show logged-in state
- Logout, check if cached pages show logged-out state
- Force-refresh after login (Ctrl+Shift+R)

### Level 4: Diagnostic Testing (as needed)

When something is broken and you need to find why.

**Who:** All three tools in coordination.

**Methodology:**

1. **Reproduce** (Visual Agent): Walk through the failing scenario, take screenshots, note exact behavior
2. **Isolate** (Playwright): Write a script that captures the full HTTP exchange during the failing step
3. **Inspect** (CLI Agent): Check server logs at the exact timestamp of the failure
4. **Hypothesize**: Based on all three data sources, form a theory
5. **Verify**: Test the hypothesis by modifying one variable at a time

**Key diagnostic checks:**

| Symptom | Check with Playwright | Check on Server |
|---------|----------------------|-----------------|
| Login succeeds but user not logged in | Are Set-Cookie headers in REST API response? | Is `wp_set_auth_cookie()` called? Any PHP warnings before it? |
| OAuth redirect fails | What's the full redirect chain? Any 301/302 losing hash fragments? | Is the callback URL configured correctly in auth provider? |
| Magic Link doesn't arrive | Is `signInWithOtp()` returning success? | Check email delivery logs, Supabase auth logs |
| Page shows stale content after login | Check Cache-Control, Vary headers | Check LiteSpeed/Varnish/CDN cache rules for logged-in users |
| Intermittent failures | Run 10x in a loop, capture all responses | Check rate limiting, database locks, transient expiry |

---

## Practical Workflows

### Workflow 1: Fix and Verify a Bug

```
1. [Visual Agent] Reproduce the bug — screenshots + console logs
2. [CLI Agent] Read code, identify root cause
3. [CLI Agent] Write fix
4. [CLI Agent] Deploy to production via SSH
5. [Visual Agent] Verify fix — walk through the same scenario
6. [Playwright] Write regression test for this bug
7. [CLI Agent] Commit fix + test
```

### Workflow 2: Pre-Deploy Validation

```
1. [CLI Agent] Make code changes
2. [Playwright] Run all existing e2e tests locally
3. [CLI Agent] Deploy to production
4. [Playwright] Run e2e tests against production
5. [Visual Agent] Quick visual smoke test on production
6. [CLI Agent] Commit if all passes, rollback if not
```

### Workflow 3: Exploratory Testing Session

```
1. [Visual Agent] Open the site, start exploring as a new user would
2. [Visual Agent] Try unexpected actions — back button, double clicks, wrong inputs
3. [Visual Agent] For each issue found:
   a. Screenshot the problem
   b. Note the exact steps to reproduce
   c. [Playwright] Write a test that captures the issue
4. [CLI Agent] Prioritize and fix issues
5. [Playwright] Run all new tests to confirm fixes
```

### Workflow 4: OAuth Flow Deep Inspection

This is the most complex scenario because it involves multiple redirects across domains.

```
1. [Playwright] Set up request interception:
   - Capture all HTTP headers on every request/response
   - Log redirect chains (301, 302, 303)
   - Record Set-Cookie headers at each step
   - Save all data to a report file

2. [Playwright] Execute OAuth flow:
   - Navigate to login page
   - Click OAuth button (Google/Facebook)
   - Follow redirects through provider
   - Capture callback URL with tokens
   - Monitor REST API call and response
   - Check cookies after REST API response
   - Follow final redirect to destination page
   - Check cookies on destination page

3. [Visual Agent] Verify the end state:
   - Is the user visually logged in?
   - Does the page show user-specific content?
   - Are there any error messages?

4. [CLI Agent] Cross-reference with server logs:
   - Check PHP error log at the timestamps from step 2
   - Verify JWT was valid
   - Confirm wp_set_auth_cookie() executed without errors
```

---

## Playwright Script Templates

### Template: OAuth Flow with Full Header Capture

```javascript
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  const requestLog = [];

  // Intercept ALL requests and responses
  page.on('response', async (response) => {
    const headers = await response.allHeaders();
    requestLog.push({
      url: response.url(),
      status: response.status(),
      headers: headers,
      setCookie: headers['set-cookie'] || null,
      timestamp: new Date().toISOString()
    });
  });

  // Step 1: Navigate to login page
  await page.goto('https://YOUR_SITE/login/');

  // Step 2: Click OAuth button
  await page.click('text=Google'); // or use specific selector

  // Step 3: Wait for OAuth redirect chain to complete
  // (Google auth page will appear — for automated testing,
  //  you may need to use Supabase Admin API to generate tokens directly)
  await page.waitForURL('**/callback**', { timeout: 30000 });

  // Step 4: Wait for REST API call to complete
  const apiResponse = await page.waitForResponse(
    resp => resp.url().includes('/wp-json/') && resp.request().method() === 'POST'
  );

  console.log('REST API Status:', apiResponse.status());
  console.log('Set-Cookie:', (await apiResponse.allHeaders())['set-cookie'] || 'NONE');

  // Step 5: Wait for final redirect
  await page.waitForNavigation();

  // Step 6: Check cookies
  const cookies = await context.cookies();
  const wpCookies = cookies.filter(c => c.name.startsWith('wordpress_'));
  console.log('WordPress cookies:', wpCookies.length > 0 ? wpCookies.map(c => c.name) : 'NONE');

  // Step 7: Save full log
  require('fs').writeFileSync('auth-flow-log.json', JSON.stringify(requestLog, null, 2));

  await browser.close();
})();
```

### Template: Cookie Diagnostic After Auth

```javascript
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Navigate to callback URL with known token
  // (Get token from Supabase Admin API or real auth flow)
  const callbackUrl = 'https://YOUR_SITE/callback/#access_token=TOKEN&token_type=bearer';

  // Intercept the REST API call
  page.on('response', async (response) => {
    if (response.url().includes('/wp-json/')) {
      const headers = await response.allHeaders();
      console.log('=== REST API Response ===');
      console.log('Status:', response.status());
      console.log('Set-Cookie:', headers['set-cookie'] || 'NOT PRESENT');
      console.log('Content-Type:', headers['content-type']);
      console.log('Cache-Control:', headers['cache-control'] || 'none');
      console.log('X-LiteSpeed-Cache:', headers['x-litespeed-cache'] || 'none');

      const body = await response.json().catch(() => null);
      console.log('Body:', JSON.stringify(body, null, 2));
    }
  });

  await page.goto(callbackUrl);
  await page.waitForTimeout(10000); // Wait for processing

  // Check what cookies exist
  const cookies = await context.cookies();
  console.log('\n=== Cookies After Auth ===');
  cookies.forEach(c => {
    console.log(`${c.name} = ${c.value.substring(0, 20)}... (domain: ${c.domain}, path: ${c.path}, secure: ${c.secure}, sameSite: ${c.sameSite})`);
  });

  await browser.close();
})();
```

### Template: Generate Valid Token via Supabase Admin API

```javascript
// Use Supabase service_role key to generate a valid session
// for testing without going through OAuth provider

const SUPABASE_URL = process.env.SUPABASE_URL;
const SERVICE_ROLE_KEY = process.env.SUPABASE_SERVICE_ROLE_KEY;

async function getTestToken(email) {
  // Option 1: Generate magic link (returns actionLink with token)
  const response = await fetch(`${SUPABASE_URL}/auth/v1/admin/generate-link`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${SERVICE_ROLE_KEY}`,
      'apikey': SERVICE_ROLE_KEY,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      type: 'magiclink',
      email: email,
      options: {
        redirectTo: 'https://YOUR_SITE/callback/'
      }
    })
  });

  const data = await response.json();
  // data.properties.action_link contains the full magic link
  // data.properties.hashed_token contains the OTP token
  return data;
}

// Option 2: Get user's access token directly
async function getUserToken(userId) {
  // List user to get their info
  const response = await fetch(`${SUPABASE_URL}/auth/v1/admin/users/${userId}`, {
    headers: {
      'Authorization': `Bearer ${SERVICE_ROLE_KEY}`,
      'apikey': SERVICE_ROLE_KEY
    }
  });
  return await response.json();
}
```

---

## Checklist: Setting Up Testing for a New Project

When you start testing a new web application, go through this checklist:

### 1. Understand the Auth Architecture
- [ ] What auth methods are available? (OAuth, Magic Link, email/password, SSO)
- [ ] What auth provider is used? (Supabase, Firebase, Auth0, custom)
- [ ] Where is the callback/redirect page?
- [ ] How are sessions created? (cookies, JWT in localStorage, both)
- [ ] What caching layer exists? (LiteSpeed, Varnish, Cloudflare, CDN)

### 2. Map the User Flows
- [ ] New user registration — each auth method
- [ ] Existing user login — each auth method
- [ ] Logout
- [ ] Password reset
- [ ] Post-login redirects (from landing pages, from login page, direct)
- [ ] Error scenarios (wrong password, expired link, provider error)

### 3. Set Up Access
- [ ] Production credentials file exists and is readable
- [ ] SSH connection to production server works
- [ ] Can read server error logs
- [ ] Can deploy files to production
- [ ] Auth provider credentials available (for generating test tokens)
- [ ] Playwright installed and working

### 4. Create Baseline Tests
- [ ] Smoke test for each auth method
- [ ] Happy path test for registration + login
- [ ] Cookie verification test (Set-Cookie headers present, correct attributes)
- [ ] Cache behavior test (logged-in vs logged-out pages)
- [ ] Error handling test (invalid tokens, expired sessions)

### 5. Establish Monitoring
- [ ] Server error log location known
- [ ] Telemetry/analytics endpoint identified
- [ ] Browser console error patterns documented
- [ ] Known false-positive errors listed (to avoid alert fatigue)

---

## Key Lessons Learned

These are patterns discovered through real debugging sessions:

1. **Set-Cookie headers are invisible to browser JavaScript.** The `fetch()` API processes cookies silently — you cannot read Set-Cookie from response headers in JS. Only Playwright (or server logs) can reveal them.

2. **Page navigation resets all browser state.** Console logs, injected scripts, network monitors — everything is lost when the page navigates away (e.g., during OAuth redirect). Playwright maintains context across navigations.

3. **LiteSpeed Cache can serve stale pages to logged-in users** if `Vary` header doesn't include cookies. Always check cache headers after login.

4. **`wp_set_auth_cookie()` fails silently if PHP headers are already sent.** Any PHP warning or error output before this call prevents cookies from being set. No error is thrown — the function just does nothing.

5. **Hash fragments (#) are preserved during browser-initiated redirects** but NOT during server-side 301/302 redirects (per HTTP spec). This matters for OAuth implicit flow tokens.

6. **Duplicate callback protection (MySQL locks) can block legitimate requests** if a previous request timed out without releasing the lock.

7. **Testing in isolation is not enough.** A function can work perfectly when called directly but fail in the real flow due to caching, CORS, cookie domain mismatches, or timing issues. Always test the full end-to-end flow.

---

*This document is a living guide. Update it as new patterns, tools, and lessons emerge from testing sessions.*
