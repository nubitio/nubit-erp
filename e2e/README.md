# POS offline regression

Run the focused spec against an isolated, seeded stack with production assets:

```sh
bash scripts/ci/e2e-local.sh e2e/pos-offline.spec.ts
```

`docker/ci-e2e-runner.sh` includes this spec in the release-critical suite. The
spec enables service workers only for its browser context; other E2E specs keep
them blocked. On the HTTP Docker stack, Chromium treats only the configured test
origin as secure so the real PWA worker can run.

The test covers:

- Creating a cash sale without network access using the cached catalog.
- Reloading the cached HTML shell and retaining the sale, items, payments, totals
  and idempotency key in IndexedDB.
- Reopening the complete POS offline and creating another sale using restored
  payment methods, warehouse defaults and API metadata.
- Updating the actual service worker while the API is unavailable and preserving
  the pending sale through the explicitly accepted controller-change reload.
- Reconnecting, losing the response after the backend accepts the sale, then
  retrying with the same key and receiving the same server sale ID.
- Finishing with two synchronized local sales, completed outbox items and no
  pending sales.

The spec temporarily changes `public/sw.js` to trigger a genuine worker update
and restores its original contents in `finally`. Run it sequentially against a
production build, without a concurrent Vite build or another PWA test.

## Bootstrap cache

The POS must have been opened online in the same tab and committed auth session
before it can reopen offline. `assets/offline/posBootstrap.ts` persists successful
read-only bootstrap queries in sessionStorage: tenant runtime config, API schema,
payment methods and warehouses. It does not persist credentials or business rows.
The snapshot is scoped to origin, user, branch and permissions, discarded on
session changes, and restored as stale so the API revalidates it on reconnection.
The product catalog and pending sales continue to use their existing IndexedDB
storage.

## Controlled updates

The tenant app checks `/health/app-version` without cache on startup, reconnection,
returning to the tab and every five minutes. A changed asset revision or waiting
worker shows **Actualizar ahora**; compatible updates also offer **Más tarde**.
The worker remains waiting until acceptance. Checkout, outbox sync and API writes
hold a shared Web Lock; activation needs an exclusive lock, including other tabs.
Browsers without Web Locks still protect operations in the current tab.

Release policy is versioned in `config/app-update.json` and compiled into the
frontend from the same source the backend reads:

- Normal compatible deploy: leave both numbers unchanged; asset hashes announce
  the new build automatically.
- Change bootstrap metadata's contract: increment `offlineContractVersion` to
  discard incompatible metadata on the new client's next startup.
- Require the new client: also raise `minimumOfflineContractVersion` to that
  version. Old clients cannot start new writes/sync; requests carrying an obsolete
  browser contract (including legacy CSRF browsers) receive HTTP 426. Auth and
  reads remain available. Control plane/account apps and header-less machine
  integrations have separate contracts and are excluded from this tenant gate.

For example, the next mandatory contract after version 1 uses:

```json
{
  "offlineContractVersion": 2,
  "minimumOfflineContractVersion": 2
}
```

Always rebuild frontend assets together with a policy change. Keep accepting
already-queued sale payloads on the server, or implement an explicit migration if
their format changes: the metadata version does not migrate business payloads.
Updates never clear IndexedDB or delete the outbox. HTTP 426 keeps queued sales
retryable, and a known mandatory update pauses sync until the client is updated.
An offline device cannot discover a deployment until it reconnects.

The first deployment of this update UI requires already-open legacy clients to
reload once to load the new notification logic. Later deployments use the prompt.

The second test holds a real checkout request open, verifies update activation
stays disabled, announces a required client through the version endpoint, and
checks that the existing checkout completes while new checkouts are blocked.

## Expired-token concurrency

`auth-expiry-concurrency.spec.ts` exercises real cookie refresh in two tabs and
six parallel API requests, as well as transient refresh failures and definitive
session expiry. `assets/shared/api/authRecovery.ts` is the common recovery layer
for direct SDK clients, provider-based CRUD/Hydra and the native outbox. The SDK's
per-instance auto-refresh is disabled in `httpTransport`.

- A single in-tab promise and the `nubit-auth-session` Web Lock coordinate
  renewal, login, logout and branch selection. Browsers without Web Locks still
  deduplicate within one tab.
- Late 401 responses reuse the credential generation already published. Each
  business request is retried at most once, keeping its body and idempotency key.
- The private request epoch tag is removed before transmission; requests from a
  replaced session are cancelled instead of replayed under another identity.
- Renewal reads the current CSRF cookie. Cookie-mode responses clear legacy
  Bearer storage; JSON-mode responses update the access token. An ordinary 403
  permission denial does not trigger refresh.
- Network/5xx failures remain recoverable and apply a short shared backoff.
  Genuine invalid refresh credentials emit one expiry notice per auth session,
  including fullscreen POS, and pause outbox sync without discarding sales.
- `/api/auth/refresh` and `/api/auth/logout` use their own refresh credential,
  independently of an expired access JWT. The global cookie CSRF protection
  still applies; a fabricated Bearer cannot bypass it. Refresh consumption and
  replacement issuance are committed together, or rolled back on failure.

## Identity module

`identity-module.spec.ts` (invitations, e-mailed links, second factor, password recovery) is
skipped unless `IDENTITY_MODULE_ENABLED=1`, and it is not part of the CI list, so by default
nothing exercises those flows. It passed 29/29 on 2026-11-17 against a stack prepared like this
(on top of `E2E_SETUP_ONLY=1 KEEP_STACK=1 bash scripts/ci/e2e-local.sh`):

- **Module on at the server.** The flag is compiled into the container: recreate `web` with
  `IDENTITY_MODULE_ENABLED=1`, then `cache:clear` + `cache:warmup`. The tables come from
  `Version20261107020000_identity_module_tables`, which the normal migration already runs.
- **Mail delivered inline.** E-mail is queued on the `async` transport; set
  `MESSENGER_TRANSPORT_DSN=sync://` on `web` (otherwise a messenger worker has to run).
- **A mail catcher.** `mailpit` exists in `docker-compose.yml` but the CI overlay parks it behind
  the `dev-mail` profile: `docker compose --profile dev-mail up -d mailpit`, and run the spec with
  `MAILPIT_URL=http://mailpit:8025` (inside the compose network) and `IDENTITY_MODULE_ENABLED=1`.
- **A tenant that can hold the users it creates.** The seeded `demo` tenant is on the `free`
  plan (3 users). The spec exceeds that and every invitation accept then fails with HTTP 429
  (`QuotaExceededException`, not rate limiting). Move the tenant to a plan with
  `"users": -1` (`pro`): `update efact.tenants set plan='pro' where name='demo'`, then
  `cache:pool:clear cache.app`, because the tenant record is cached.
- **Attempt limiter.** `password_reset.max_attempts: 0` (disables the identity module's
  per-IP/per-identity limiter) was set in the passing run. It was not isolated, so it may or may
  not be needed.

Run the spec once per fresh stack: it creates users and leaves them behind.
