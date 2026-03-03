# Orchestration Plan — SLICE-005

| Field        | Value                                                                         |
|--------------|-------------------------------------------------------------------------------|
| slice        | SLICE-005                                                                     |
| title        | Hardening de seguridad: APP_SECRET, security headers y endpoints públicos     |
| status       | in_progress                                                                   |
| branch       | slice/005-security-hardening                                                  |
| created_at   | 2026-03-02                                                                    |
| orchestrator | agt-orchestrate                                                               |

---

## Summary

Three distinct security gaps are addressed in this slice:

1. **SEC-3 (TASK-013)** — The FrankenPHP `Caddyfile` emits no security headers.
   Every response must include HSTS, CSP, X-Frame-Options, X-Content-Type-Options,
   Referrer-Policy, and Permissions-Policy.

2. **SEC-4 (TASK-014)** — `/api/settings` and `/api/sales/report` are currently
   `PUBLIC_ACCESS` in `security.yaml`, exposing tenant data without authentication.
   Both endpoints must be changed to `ROLE_USER`.

3. **SEC-1 (TASK-015)** — `JWTManager` already throws `LogicException` when
   `APP_SECRET` is empty, but only on first use. An `AppSecretBootListener`
   (kernel.request at priority 1025) must eagerly instantiate `JWTManager` at boot
   so the error surfaces immediately on deploy rather than at first login.
   The `.env` `APP_SECRET` placeholder must be changed to `CHANGEME_generate_with_bin2hex_random_bytes_32`.

4. **SEC-2 (TASK-016)** — No rotation runbook exists. `docs/runbooks/rotate-secrets.md`
   must be created with step-by-step instructions for S3, GitHub PAT, Bill API token,
   and `APP_SECRET`.

5. **T5 (TASK-017)** — Regression tests covering 401 without token and 200 with valid
   token for `/api/settings` and `/api/sales/report`.

6. **T6 (TASK-018)** — Final QA gate: grumphp green, header verification,
   boot-failure verification with empty `APP_SECRET`.

No schema migrations required. All changes are PHP, YAML, Caddyfile, and Markdown.

---

## Artifact Registry (this slice)

| ID      | Type        | Owner       | File / Description                                                            |
|---------|-------------|-------------|-------------------------------------------------------------------------------|
| ART-015 | config      | agt-devops  | `docker/frankenphp/Caddyfile` — security headers block added                 |
| ART-016 | config      | agt-backend | `config/packages/security.yaml` — `/api/settings` + `/api/sales/report` → ROLE_USER |
| ART-017 | code        | agt-backend | `src/Core/EventListener/AppSecretBootListener.php` — kernel.request eager check |
| ART-017b| config      | agt-backend | `.env` — `APP_SECRET` placeholder changed to `CHANGEME_*`                    |
| ART-018 | docs        | agt-devops  | `docs/runbooks/rotate-secrets.md` — secrets rotation runbook                 |
| ART-019 | test        | agt-qa      | `tests/Core/Security/AccessControlTest.php` — 401/200 regression tests       |
| ART-020 | gate-report | agt-qa      | Gate-2 checklist: grumphp + headers + boot-fail verification                 |

---

## Waves

### Wave 1 — Parallel (TASK-013, TASK-014, TASK-015, TASK-016)

All four tasks are fully independent and can run concurrently on the same branch.
Each modifies a distinct file with no overlapping concerns.

---

#### TASK-013 — SEC-3: Security headers in Caddyfile

```
ORCHESTRATOR → agt-devops
TASK:     TASK-013
SLICE:    SLICE-005
INPUTS:   docker/frankenphp/Caddyfile (current, 60 lines)
EXPECTED OUTPUT: ART-015 type=config
DEADLINE GATE:   vendor/bin/grumphp run must pass
---
Add a security headers block to `docker/frankenphp/Caddyfile`.

The file currently has a `route { ... }` block (lines 30–60) with static
file serving, uploads, and the php_server directive. Add the following
`header` directive INSIDE the `route { ... }` block, immediately after the
opening `root * /app/public` line (before the `encode` directive), so that
the headers apply to all responses (static files, PHP, uploads):

    header {
        Strict-Transport-Security  "max-age=31536000; includeSubDomains"
        Content-Security-Policy    "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:"
        X-Frame-Options            "DENY"
        X-Content-Type-Options     "nosniff"
        Referrer-Policy            "strict-origin-when-cross-origin"
        Permissions-Policy         "camera=(), microphone=(), geolocation=()"
    }

Notes:
- `style-src 'unsafe-inline'` is intentional — required while DevExtreme
  injects inline styles. Accepted trade-off for v1.
- `img-src data:` is required for the base64 logo on receipts.
- The block must appear before the @static matcher so static files also
  receive the headers.
- Do NOT add an `X-Powered-By` suppression directive (not required).
- Preserve all existing directives verbatim.

Acceptance:
- All 6 headers are present in the `header { }` block.
- The Caddyfile remains syntactically valid (no duplicate directives,
  proper indentation, closing braces match).
- vendor/bin/grumphp run passes (phpcs/phpstan/phpunit do not touch Caddyfile,
  but the overall suite must stay green).
```

---

#### TASK-014 — SEC-4: Restrict endpoints in security.yaml

```
ORCHESTRATOR → agt-backend
TASK:     TASK-014
SLICE:    SLICE-005
INPUTS:   config/packages/security.yaml (lines 50, 52)
EXPECTED OUTPUT: ART-016 type=config
DEADLINE GATE:   vendor/bin/grumphp run must pass
---
In `config/packages/security.yaml`, change two `access_control` entries
from `PUBLIC_ACCESS` to `ROLE_USER`:

BEFORE (line 50):
    - { path: ^/api/settings, roles: PUBLIC_ACCESS }

AFTER:
    - { path: ^/api/settings, roles: ROLE_USER }

BEFORE (line 52):
    - { path: ^/api/sales/report, roles: PUBLIC_ACCESS }

AFTER:
    - { path: ^/api/sales/report, roles: ROLE_USER }

The `/api/setup` entry on line 51 (`PUBLIC_ACCESS`) must NOT be changed —
it is explicitly out of scope per SLICE-005 §1 "Out of scope".

Preserve all other lines verbatim. No other changes to security.yaml.

Acceptance:
- `grep 'PUBLIC_ACCESS' config/packages/security.yaml` does NOT return
  lines for /api/settings or /api/sales/report.
- Both lines show `roles: ROLE_USER`.
- vendor/bin/grumphp run passes.
- The existing JWTAuthenticator and /api/auth/* tests remain green.
```

---

#### TASK-015 — SEC-1: APP_SECRET boot validation (eager JWTManager instantiation)

```
ORCHESTRATOR → agt-backend
TASK:     TASK-015
SLICE:    SLICE-005
INPUTS:   src/Core/Security/Auth/JWTManager.php,
          src/Core/EventListener/TenantListener.php (reference for listener pattern),
          config/services.yaml (if autoconfigure is set),
          .env (line 3: APP_SECRET=)
EXPECTED OUTPUT: ART-017 type=code, ART-017b type=config
DEADLINE GATE:   vendor/bin/grumphp run must pass
---
## Goal
`JWTManager::__construct()` already throws `LogicException` when APP_SECRET is
empty (line 21–23 of JWTManager.php). The problem is that Symfony's DI container
is lazy — JWTManager is only instantiated on first use (first login attempt), not
at kernel boot. We must force eager instantiation so a deploy with an empty
APP_SECRET fails immediately, not silently at runtime.

## Preferred approach (simpler, safer than CompilerPass)
Create a `kernel.request` event listener at priority **1025** (higher than
TenantListener at 1024) that injects `JWTManager` in its constructor and does
nothing in `__invoke` beyond touching `$this->jwtManager` to force construction.

### File to create: `src/Core/EventListener/AppSecretBootListener.php`

```php
<?php

declare(strict_types=1);

namespace App\Core\EventListener;

use App\Core\Security\Auth\JWTManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Forces eager instantiation of JWTManager at the first kernel.request event.
 * JWTManager throws LogicException in its constructor when APP_SECRET is empty,
 * surfacing the misconfiguration at boot time rather than on the first login attempt.
 */
#[AsEventListener(priority: 1025)]
readonly class AppSecretBootListener
{
    public function __construct(
        private JWTManager $jwtManager,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        // Instantiation of JWTManager in the constructor is sufficient.
        // This listener exists solely to trigger DI construction at the first request,
        // surfacing an empty APP_SECRET LogicException before any business logic runs.
        unset($event); // explicit: $event is not used
    }
}
```

## .env change
On line 3 of `.env`, change:
    APP_SECRET=
to:
    APP_SECRET=CHANGEME_generate_with_bin2hex_random_bytes_32

This documents the expected format and prevents future deployments from
accidentally shipping an empty secret.

## Acceptance
- `AppSecretBootListener` exists at `src/Core/EventListener/AppSecretBootListener.php`.
- The listener is tagged as `kernel.request` (via `#[AsEventListener]` attribute)
  with priority 1025.
- In a unit test (or manual verification), constructing `AppSecretBootListener`
  with a `JWTManager` that has an empty secret raises `LogicException`.
- `.env` line 3 reads `APP_SECRET=CHANGEME_generate_with_bin2hex_random_bytes_32`.
- vendor/bin/grumphp run passes (phpstan, phpcs, phpmd, phpunit all green).
- No regressions in `tests/Core/Security/Auth/JWTTenantBindingTest.php`.
```

---

#### TASK-016 — SEC-2: Secrets rotation runbook

```
ORCHESTRATOR → agt-devops
TASK:     TASK-016
SLICE:    SLICE-005
INPUTS:   docs/slices/SLICE-005.md §4.4, .env (lines 55–61, 68, 78–82)
EXPECTED OUTPUT: ART-018 type=docs
DEADLINE GATE:   vendor/bin/grumphp run must pass (doc file only, no PHP linting impact)
---
Create the directory `docs/runbooks/` and the file `docs/runbooks/rotate-secrets.md`.

The runbook must contain step-by-step instructions for rotating each of the
following secrets. Write in English. Use numbered steps per secret section.

### Required sections

1. **APP_SECRET** — How to generate a new secret and deploy it:
   - Generate: `php -r "echo bin2hex(random_bytes(32));"`
   - Update `.env.local` on each server (never commit the real value).
   - Invalidate all existing JWT sessions (all tokens signed with the old secret
     become invalid immediately — users must re-login).
   - Rolling restart of FrankenPHP containers.

2. **S3_ACCESS_KEY / S3_ACCESS_SECRET** (Wasabi S3) — Steps:
   - Log in to Wasabi console → IAM → Access Keys.
   - Create a new key pair.
   - Update `.env.local` on all servers with new values.
   - Restart containers to pick up new env vars.
   - Verify S3 uploads work (smoke test: upload a test file).
   - Delete the old key pair from Wasabi console.

3. **GITHUB_TOKEN** (GitHub PAT) — Steps:
   - Go to GitHub → Settings → Developer Settings → Personal Access Tokens.
   - Generate a new token with the same scopes as the current one.
   - Update the token in CI secrets (GitHub Actions) and in `.env.local` if used
     in the app directly.
   - Revoke the old token.

4. **BILL_API_TOKEN** (billing API) — Steps:
   - Contact the billing API provider or use their dashboard to generate a new token.
   - Update `BILL_API_TOKEN` in `.env.local` on all servers.
   - Restart containers.
   - Run a billing API smoke test to confirm the new token works.

### Additional requirements
- Add a section "When to rotate" listing trigger conditions:
  (a) Suspected or confirmed credential exposure (commit in git, shared in chat, etc.)
  (b) Employee/contractor offboarding
  (c) Annual rotation policy (recommended)
- Add a section "Emergency contacts" as a placeholder with `[TODO: fill in]` entries.
- Note at the top of the file: "See Q3 in SLICE-005 open questions — verify with
  ops team whether these secrets are live in production before executing rotation."

Acceptance:
- File exists at `docs/runbooks/rotate-secrets.md`.
- All four secrets (APP_SECRET, S3, GitHub PAT, BILL_API_TOKEN) have their own
  numbered-step section.
- The command `php -r "echo bin2hex(random_bytes(32));"` appears verbatim.
- vendor/bin/grumphp run passes (GrumPHP does not lint Markdown).
```

---

### Gate 1 — after Wave 1

Run `vendor/bin/grumphp run`.

- **PASS** → commit state, create PR, proceed to Wave 2
- **FAIL** → return to responsible agent (identified by which file the linter errors in);
  max 2 retries before human escalation

---

### Wave 2 — QA (TASK-017, TASK-018)

Both QA tasks can run sequentially (TASK-017 produces the test artifact needed
by TASK-018 for the final checklist).

---

#### TASK-017 — T5: Regression tests — 401/200 access control

```
ORCHESTRATOR → agt-qa
TASK:     TASK-017
SLICE:    SLICE-005
INPUTS:   ART-016 (security.yaml change),
          tests/Core/Security/Auth/JWTTenantBindingTest.php (reference for test style),
          tests/Controller/Api/MakeInvoiceControllerTest.php (reference for controller test style),
          src/Core/Security/Auth/JWTManager.php,
          src/Core/Security/Auth/JWTAuthenticator.php (if it exists)
EXPECTED OUTPUT: ART-019 type=test
DEADLINE GATE:   vendor/bin/grumphp run must pass
---
Create `tests/Core/Security/AccessControlTest.php` with PHPUnit unit tests that
verify the access control changes made in TASK-014 (ART-016).

## Tests required

Since the project uses PHPUnit unit tests (not Symfony WebTestCase / HTTP client
integration tests — see existing test style in `tests/Controller/Api/`), implement
the following unit-level tests:

### Test class: `App\Tests\Core\Security\AccessControlTest`

**Test 1 — testSettingsEndpointRequiresAuthentication**
- Verify that the `security.yaml` access_control entry for `^/api/settings`
  uses `ROLE_USER` (not `PUBLIC_ACCESS`).
- Implementation: parse `config/packages/security.yaml` with `Symfony\Component\Yaml\Yaml::parseFile()`
  and assert the relevant access_control entry.

**Test 2 — testSalesReportEndpointRequiresAuthentication**
- Same as Test 1 but for `^/api/sales/report`.

**Test 3 — testJWTManagerRejectsEmptySecret**
- Verify that constructing `JWTManager` with an empty string throws `LogicException`.
- Use `$this->expectException(LogicException::class)`.
- `new JWTManager('', new NullLogger())` must throw.

**Test 4 — testJWTManagerAcceptsNonEmptySecret**
- Verify that `new JWTManager('some-secret', new NullLogger())` does NOT throw.

**Test 5 — testAppSecretBootListenerExists**
- Verify that `src/Core/EventListener/AppSecretBootListener.php` exists
  using `file_exists()` or `class_exists('App\Core\EventListener\AppSecretBootListener')`.

## Style rules (match existing tests)
- `declare(strict_types=1);`
- `final class` with `extends TestCase`
- `self::` (not `$this->`) for assertions, except `$this->expectException()`
- No Symfony kernel boot in tests — pure unit tests only
- NullLogger for JWTManager construction

## Acceptance
- All 5 tests pass with `vendor/bin/phpunit`.
- vendor/bin/grumphp run passes.
- No existing tests broken.
```

---

#### TASK-018 — T6: Final gate — grumphp + header verification + boot check

```
ORCHESTRATOR → agt-qa
TASK:     TASK-018
SLICE:    SLICE-005
INPUTS:   ART-015, ART-016, ART-017, ART-017b, ART-018, ART-019,
          Gate-1 report,
          docs/slices/SLICE-005.md §9 (9 acceptance criteria)
EXPECTED OUTPUT: ART-020 type=gate-report
DEADLINE GATE:   all 9 acceptance criteria in SLICE-005 §9 satisfied
---
Produce a final gate report verifying all 9 SLICE-005 acceptance criteria.

For each criterion, produce a PASS/FAIL verdict with evidence.

### Criteria to verify

**AC-1** — `GET /api/settings` without token → 401
- Evidence: test `testSettingsEndpointRequiresAuthentication` in ART-019 PASSES,
  AND `config/packages/security.yaml` shows `ROLE_USER` for `/api/settings`.

**AC-2** — `GET /api/sales/report` without token → 401
- Evidence: `testSalesReportEndpointRequiresAuthentication` PASSES,
  AND `security.yaml` shows `ROLE_USER` for `/api/sales/report`.

**AC-3** — Authenticated ROLE_USER request to `GET /api/settings` → 200 (no regression)
- Evidence: no change to the controller itself; the Symfony security layer
  passes authenticated requests through. Confirm no controller for `/api/settings`
  was deleted or modified.

**AC-4** — All 6 security headers present in Caddyfile `header { }` block
- Evidence: inspect `docker/frankenphp/Caddyfile` for all 6 headers:
  - `Strict-Transport-Security`
  - `X-Frame-Options`
  - `X-Content-Type-Options`
  - `Content-Security-Policy`
  - `Referrer-Policy`
  - `Permissions-Policy`

**AC-5** — Boot fails with empty APP_SECRET in prod
- Evidence: `AppSecretBootListener` exists; its constructor injects `JWTManager`;
  `JWTManager` throws `LogicException` when secret is empty (verified in ART-019).
  `testJWTManagerRejectsEmptySecret` PASSES.

**AC-6** — `.env` contains no real secrets; all sensitive fields are empty or `CHANGEME_*`
- Evidence: check `.env` line 3: `APP_SECRET=CHANGEME_generate_with_bin2hex_random_bytes_32`.
  Verify no other obviously-real credentials appear (check `BIRD_ACCESS_KEY`,
  `DATABASE_URL` — these contain placeholder/dev values, not prod credentials).
  Note: `MAILER_DSN` line 68 contains `SecurePassword` — flag this as a
  **WARNING** (not a blocker for this slice, but should be addressed in a follow-up).

**AC-7** — `docs/runbooks/rotate-secrets.md` exists with all 4 secret sections
- Evidence: file exists; grep for `APP_SECRET`, `S3_ACCESS`, `GITHUB_TOKEN`,
  `BILL_API_TOKEN` headings.

**AC-8** — `vendor/bin/grumphp run` passes (phpstan, phpcs, phpmd, phpunit)
- Evidence: run `vendor/bin/grumphp run` and capture output. All tasks must be green.

**AC-9** — No regressions in JWTAuthenticator tests and /api/auth/* endpoint tests
- Evidence: `vendor/bin/phpunit tests/Core/Security/` passes. Specifically,
  `JWTTenantBindingTest` all 5 tests remain PASS.

### Report format
Produce a Markdown checklist:
```
## SLICE-005 Gate-2 Report — [date]

| AC  | Criterion (short)                            | Status | Evidence |
|-----|----------------------------------------------|--------|----------|
| AC-1 | /api/settings → 401 without token           | PASS   | ... |
| AC-2 | /api/sales/report → 401 without token       | PASS   | ... |
| AC-3 | /api/settings → 200 with auth (no regression)| PASS  | ... |
| AC-4 | 6 security headers in Caddyfile             | PASS   | ... |
| AC-5 | Boot fails with empty APP_SECRET            | PASS   | ... |
| AC-6 | .env has no real secrets                    | PASS   | ... |
| AC-7 | rotate-secrets.md exists                    | PASS   | ... |
| AC-8 | grumphp run green                           | PASS   | ... |
| AC-9 | No JWT/auth test regressions                | PASS   | ... |

**Overall: PASS / FAIL**
```

If any criterion is FAIL, emit a BLOCKER with the criterion ID before
returning the report to the orchestrator.
```

---

## Gate Checkpoints

| Gate   | Trigger                         | Criteria                                                          |
|--------|---------------------------------|-------------------------------------------------------------------|
| Gate-1 | After Wave 1 completes          | `vendor/bin/grumphp run` green (phpstan + phpcs + phpmd + phpunit) |
| Gate-2 | After Wave 2 / QA final sign-off | All 9 acceptance criteria in SLICE-005 §9 PASS                   |

---

## Escalation Policy

If the same gate fails twice consecutively for the same task, create a GitHub
issue labelled `blocked` and halt until human resolves.

---

## Events Log (populated during execution)

| Timestamp (UTC)         | Event          | Details                           |
|-------------------------|----------------|-----------------------------------|
| (populated at start)    | SliceStarted   | SLICE-005                         |
| (after T13 hand-off)    | TaskHandedOff  | TASK-013 → agt-devops             |
| (after T14 hand-off)    | TaskHandedOff  | TASK-014 → agt-backend            |
| (after T15 hand-off)    | TaskHandedOff  | TASK-015 → agt-backend            |
| (after T16 hand-off)    | TaskHandedOff  | TASK-016 → agt-devops             |
| (after Gate-1)          | GatePassed     | Gate-1 grumphp run                |
| (after PR creation)     | PRCreated      | slice/005-security-hardening      |
| (after T17 hand-off)    | TaskHandedOff  | TASK-017 → agt-qa                 |
| (after T18 hand-off)    | TaskHandedOff  | TASK-018 → agt-qa                 |
| (after Gate-2)          | GatePassed     | Gate-2 all 9 AC                   |
| (final)                 | SliceCompleted | SLICE-005                         |
