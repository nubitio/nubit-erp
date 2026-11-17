# ADR-020: Path-based routing for the tenant SPA (retire HashRouter)

Date: 2026-11-17
Status: Accepted (2026-11-17, approved by the product owner as written: data router with a splat route, fragment-carried tokens, permanent legacy redirect)

## Context

The tenant SPA uses `HashRouter` (`assets/app/AppProviders.tsx`), so every route
is `/#/…` and the server only ever sees `/`. This was chosen to avoid colliding
with Symfony routes. The other two SPAs in this repo already use `BrowserRouter`
with real paths (control plane under `/admin`, account portal under `/register`
and `/account`), each served by a Symfony controller that lists its routes.

Reasons to move the tenant SPA to real paths:

- `useBlocker` (dirty-form guard in ADR-019) only works under a data router;
  `HashRouter` is not one.
- Real URLs for deep links, server logs, metrics and e-mails; consistency with
  `/admin` and `/account`.
- URL-addressable list state and tabs (`?tab=`, filters, page) read naturally
  instead of living after a `#`.

### What was verified (2026-11-17)

**The collision the hash avoids is small and enumerable, but not empty.** The
real router (`debug:router`, 591 routes, 41 outside `/api`) has these non-API
namespaces: `/`, `/account/*`, `/admin/*`, `/buscar` (public voucher search),
`/health*`, `/logo`, `/receipt/*` (public vouchers), `/register*`, `/webhook/*`,
plus static `/build`, `/uploads`, `/sw.js`, `/workbox-*.js`. Of the 129 paths the
tenant SPA answers (`tests/Fixtures/spa-routes.json`), exactly **one** collides:
the stock-receipt screen `crudRoute('/receipt', …)` (`/receipt`, `/receipt/:id?`,
and `navigate('/receipt/new')`) versus the public `/receipt/{payload}` route.
An earlier draft of this ADR said there were no collisions; that came from
reading attributes and missing `ReceiptController`, and was wrong. The public
voucher URLs are printed on QR codes and sent by e-mail, so they cannot move: the
SPA route is the one to rename (D7).

**But a prefix footgun exists.** `config/packages/security.yaml` has
`access_control: { path: ^/account, roles: PUBLIC_ACCESS }` with no boundary.
Under path routing it would also match `/accounting`, `/accounts-payable` and
`/accounts-receivable`. Those URLs only return the public HTML shell (data is
behind `/api`, which has its own rules), so this is not a data leak, but the
rule is wrong and must become `^/account(/|$)`.

**Four things the hash was doing silently:**

1. **Secrets stay out of server logs.** `ImpersonateModal` says so explicitly:
   the `#` keeps the impersonation token "never sent to the server". The same
   holds for `#/reset-password?token=…` and `#/accept-invitation?token=…`
   (`IdentityMailSubscriber`), read client-side with `useSearchParams`
   (`IdentityPages.tsx`, `ImpersonateBridgePage.tsx`). Caddy writes JSON access
   logs, which include the request URI and query string. With
   `/reset-password?token=…` those tokens would land in logs and any proxy.
2. **Offline reload works for every route.** The service worker caches
   navigations with `NetworkFirst` keyed by URL (`vite.config.js`,
   `navigateFallback: null`, cache `app-shell`), and `warmAppShell()`
   (`assets/offline/appUpdate.ts`) strips the hash and caches one URL. Because
   every route is `/`, one cache entry serves the whole SPA offline. With real
   paths, an offline reload of `/billing/sales` works only if that exact URL was
   visited online before — a regression for the POS, which has an e2e for exactly
   this (`e2e/pos-offline.spec.ts`).
3. **No server round trip on navigation** — unchanged: client navigation still
   does not hit the server; only full reloads and entry from outside do.
4. **Legacy URLs exist in the wild:** e-mail links (`IdentityMailSubscriber` ×2,
   `account_reminder.html.twig`), installed PWAs (`start_url: '/#/pos'`),
   bookmarks, `docs/cashier-user-manual.md`, 100 occurrences across 36 e2e
   files, and ~160 lines of hash plumbing in `assets/shared/lib/authRedirect.ts`.

## Decision

Migrate the tenant SPA to real paths, with these constraints.

### D1 — Data router with a single splat route

Replace `HashRouter` with `createBrowserRouter([{ path: '*', element: <App /> }])`
+ `RouterProvider`. The existing `<Routes>` tree keeps working as descendant
routes, so no route is rewritten, and the app gains `useBlocker`, `useNavigation`
and the option of loaders later. A plain `BrowserRouter` is rejected: it would
leave ADR-019's dirty-form guard impossible.

### D2 — One Symfony catch-all, with explicit exclusions

`HomeController` gains a `GET /{path}` route (`spa_catch_all`, priority -100)
that renders the same shell template as `/`, with a requirement that excludes
`api`, `admin`, `account`, `register`, `buscar`, `receipt`, `build`, `uploads`,
`health`, `logo`, `webhook`, the static directories, `.well-known`, `sw.js`,
`workbox-…`, `_…` (profiler), and **any path ending in a file extension** (a
missing asset must 404, not return HTML with status 200, which would also poison
the service worker's asset caches). Two implementation traps found by the tests:
Symfony's compiled URL matcher drops an end anchor `$` inside a lookahead, so the
requirement ends paths with `(?!.)`; and on a 404 the router retries a GET with a
trailing slash (`/favicon.ico` → `/favicon.ico/`), so the extension rule must
reject `.ext/` as well, otherwise a missing file redirects into the shell.
Explicit routes win by priority; unknown `/api/*` keeps returning JSON 404.
Do not enumerate the ~110 SPA paths in PHP (the account-portal approach does not
scale here and would drift from `app-routes.tsx`).

### D3 — Secrets travel in the URL fragment, not the path or query

Path routing applies to navigation, not to bearer tokens. Token-carrying links
become `/reset-password#token=…`, `/accept-invitation#token=…` and
`/impersonate#token=…`. The page reads the fragment once and immediately
removes it with `history.replaceState`. The server sees only the path, so logs
stay clean exactly as today, and fragments are never sent in `Referer`. A small
`useFragmentParams()` hook replaces `useSearchParams` for those three pages.

### D4 — Service worker: one shell for every navigation

Replace the URL-keyed navigation cache with a handler that, for any same-origin
GET navigation outside the excluded prefixes, tries the network and on failure
serves the cached `/` shell. `warmAppShell()` caches `/` (not the current URL).
`manifest.start_url` becomes `/pos`. Excluded prefixes mirror D2 so `/admin` and
`/account` are never answered with the tenant shell. The existing
`pos-offline` e2e is the acceptance gate and must pass unchanged apart from URLs.

### D5 — Legacy `/#/…` compatibility, permanently

At boot, before the router mounts: if `location.pathname === '/'` and
`location.hash` starts with `#/`, `history.replaceState` to the path (and
convert a legacy `#/reset-password?token=…` into the fragment form of D3, and map
the renamed `#/receipt…` to `/stock-receipt…`, see D7). It is
a few lines, costs nothing, and covers e-mails already sent, bookmarks and
installed PWAs. It is not scheduled for removal. `authRedirect.ts` is reduced to
path handling plus this one legacy normaliser.

### D6 — Guard against future collisions

A PHPUnit test (CI has PHP 8.5) loads the Symfony router and a generated list of
the SPA's route paths (emitted from `app-routes.tsx` at build/test time) and
fails if any SPA path resolves to a route other than the catch-all, or if a
reserved prefix resolves to it. This replaces "the hash keeps us safe" with an
executable check. Also fix `^/account` → `^/account(/|$)` (and review
`^/register`, `^/admin`) in `security.yaml`.

### D7 — Rename the stock-receipt screen

`/receipt` (SPA) → `/stock-receipt`, consistent with `/stock-adjustment`. It is
renamed in step 2, not step 1, because it changes a visible URL; until then the
collision test carries it as an explicit, exactly-matched `KNOWN_COLLISIONS`
entry that fails when the debt is paid, so it can neither grow nor be forgotten.

## Rollout (three independently shippable steps)

1. **Server + service worker + guards, no behaviour change for users.**
   Implemented 2026-11-17: the catch-all route and its exclusions (D2), the
   `security.yaml` boundary fix (`^/account(?:/|$)`, `^/register(?:/|$)`), the SPA
   route manifest (`tests/Fixtures/spa-routes.json`, generated and drift-checked
   by `assets/app/shell/spaPaths.test.ts`) and the router collision test
   `tests/Efact/Web/SpaRoutingTest.php` (D6), and the service-worker handler with
   `warmAppShell()` caching `/` (D4; `assets/offline/shellNavigation.js`). The app
   still runs on `HashRouter`, so every URL behaves as before. The legacy
   normaliser (D5) is **not** in this step: it redirects `/#/x` to `/x`, which is
   only correct once the router is path-based, so it ships with step 2.
2. **Flip the router.** `createBrowserRouter` (D1), the legacy normaliser (D5),
   rename `/receipt` → `/stock-receipt` and empty `KNOWN_COLLISIONS` (D7),
   `useFragmentParams` (D3), replace `location.hash` uses (`LoginForm`,
   `BranchPicker`), `<a href="#/…">` → `<Link>` (`CustomerSubscriptionRunPage`),
   simplify `authRedirect.ts`, `start_url`, rewrite e2e URLs (mechanical:
   `/#/x` → `/x`), update the manual. Because clients upgrade through
   `skipWaiting: false` / idle reload (`appUpdate.ts`), old hash clients and new
   path clients coexist; step 1 guarantees both are served.
3. **Flip emitters.** `IdentityMailSubscriber`, `account_reminder.html.twig`,
   `ImpersonateModal` generate path + fragment URLs (D3). Implemented 2026-11-17:
   invitation and reset links are `/accept-invitation#token=…` and
   `/reset-password#token=…`, the support bridge is `/impersonate#token=…`, and the
   registration reminder points at `/register` (it used to point at `/#/register`,
   which landed in the tenant SPA and bounced to login). Pinned by
   `IdentityMailSubscriberTest` and `impersonationUrl.test.ts`.

Verified in a real browser against the dev stack (Playwright, 2026-11-17): a legacy
`/#/billing/sales` logs in and returns to `/billing/sales`; a hard reload on a deep
route renders the page; `/stock-receipt` renders; `/#/receipt` maps to it;
`/reset-password#token=…` renders the form, removes the token from the address bar and
the server log contains no trace of it; the legacy `/#/reset-password?token=…` and
`/#/register` map correctly. **Not verified:** consuming a real token end to end. The
identity module is off by default (`IDENTITY_MODULE_ENABLED=0`), so `identity-module.spec.ts`
is skipped in CI; it needs the flag, the `nubit_*` tables and a mail catcher to run.
Do that before relying on the e-mailed links in production.

Rolling back step 2 is a revert; steps 1 and 3 are harmless on their own.

## Consequences

Positive: real URLs and a data router (unblocks ADR-019's dirty guard and
URL-state grids), consistent with the other SPAs, an executable collision check
instead of an implicit one, and a fix for an over-broad security prefix.

Negative / costs: service-worker and offline behaviour must be re-verified (the
highest-risk part); a full reload of any route now costs one PHP request for the
shell (previously only `/`); ~36 e2e files change mechanically; token-bearing
pages need the fragment pattern and cannot use `useSearchParams`; PHP-side
catch-all adds one route that must keep its exclusions in sync with new
top-level server routes (guarded by D6).

## Alternatives considered

1. **Keep HashRouter.** Zero risk, but permanently blocks `useBlocker` and
   keeps non-standard URLs; the collision it avoids is solvable (D2, D6).
2. **Plain `BrowserRouter`.** Simplest migration, but no data-router features,
   so ADR-019's dirty guard would still need the router-less workaround.
3. **Mount the SPA under a prefix (`/app/…`).** Removes all collisions by
   construction and keeps `/` free, at the price of a longer URL and a redirect
   from `/`. A reasonable fallback if D2's exclusion list proves hard to keep
   correct; not preferred because it still needs the catch-all, the SW work and
   the legacy redirect.
4. **Enumerate SPA routes in Symfony** (the `AccountPortalSpaController` style).
   Rejected: ~110 paths that would drift from `app-routes.tsx`.
5. **Tokens in the query string** for simplicity. Rejected: Caddy access logs and
   any intermediary would record them (D3).

## Validation

Step 1 is validated by the collision test (20 tests against the real router in
the PHP 8.5 container) and by the unit tests for the manifest and the
service-worker functions. Not verified in step 1: an end-to-end HTTP run through
Caddy/FrankenPHP and the tenant listener (the dev stack's ERP database was not
running), and `e2e/pos-offline.spec.ts` against the new service worker. Step 2 by the full e2e suite, with `pos-offline`, `identity-module`
and `auth-*` as the gates, plus a manual check that no token appears in the
Caddy access log for the three token flows. Not yet verified: how Workbox
behaves with the replaced navigation handler across an SW update while a POS sale
is pending (covered by `pos-offline`, but must be run, not assumed).
