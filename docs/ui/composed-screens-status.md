# Composed UI refactor: plan and status

Last updated 2026-11-18. Read with `docs/ui/composed-screens.md` (recipe and gotchas) and
`docs/adr/ADR-019-*.md` (decision), `ADR-020-*.md` (path routing).

## Where we are

| Item | State |
| --- | --- |
| ADR-019 composed UI (components in the ERP, no new package) | Accepted, amended |
| ADR-020 path routing instead of HashRouter | Done and verified (steps 1 to 3, e2e, identity flows) |
| Foundation: `assets/shared/ui/{data-grid,entity-form}`, `formErrors`, `downloadBlob` | Done, tested |
| Customers pilot (`/customer`) | Done: unit tests, browser run against the real API, e2e on the clean stack |
| POS unit counter on weighted lines (per-tenant setting) | Done and committed |
| Customer detail panel with receivables (the "new capability" the ADR asks a pilot to prove) | Not started |
| Next waves | Catalog/Products, then Purchasing/AP/Payroll/Fixed assets, Sales last |

## Next steps, in order

1. **Customer detail panel with receivables.** Opens from the row; shows the customer's open
   documents and balance. This is the evidence that composed screens give users something the
   schema-driven engine could not. Check which endpoint exposes receivables before designing.
2. **nubit-react PR: dark-theme contrast.** The active segmented-control button (3.47:1) and the
   secondary button (4:1) fail WCAG AA in dark mode. Protected `main`: branch, PR, merge when CI
   is green.
3. **Catalog/Products wave**, following the recipe. The products form is the biggest win:
   variants, units and prices deserve a real layout instead of generated fields.
4. Delete the schema-driven wiring (and the old `CustomerPage`/`CustomerFields`/`model`) of a
   screen only when nothing else embeds it. `SalePage` and `PriceListPage` still use the old
   customer form, so that waits for the Sales wave.

## Open decisions for the owner

- Whether the customer panel ships in the pilot or with the Catalog wave.
- The 3 failing new tests in the working tree (see below): whether they describe fixes that are
  still to be written.

## Not part of this work (uncommitted in the working tree when this was written)

Operational-integrity work that was already in progress, left untouched on purpose:
`src/Reporting/Command/OperationalIntegrityCommand.php`,
`src/Reporting/Service/OperationalIntegrityQuery.php`, their tests, `docs/operations/data-integrity.md`,
`src/Inventory/Core/Service/StockProjectionWriter.php` (not referenced by anything yet),
`tests/Accounting/Service/TrialBalanceQueryTest.php` (2 failing),
`tests/Restaurant/RestaurantOrderLifecycleTest.php` (1 failing).

## Known limits of what was verified

- The POS unit counter has unit and component tests but was never driven in a browser with a
  tenant that has the setting on (e2e has no weighted-product fixture).
- Save and delete of customers were verified against the dev API with prefixed test data
  (`QA-PW`, removed at the end). Re-run that kind of pass for every new screen.

## Local environment notes

- ERP e2e on a clean stack: `scripts/ci/e2e-local.sh` (isolated compose project `nubit-e2e`). Its
  OpenAPI compatibility step fails when `assets/api/types.generated.ts` is stale; regenerate it
  from `php bin/console api:openapi:export` and convert with `openapi-typescript`.
- Two other projects on this machine own host ports 3306 and 6379. The dev stack needs a local,
  uncommitted compose override that moves the ERP database to another host port and publishes no
  host port for Redis. Without the Redis service on the project network every save returns 500
  (`getaddrinfo for redis failed`).
- The dev `web` container sometimes crashes (`zend_mm_heap corrupted`) and corrupts
  `var/cache/dev`: run `make cache-clear` and reload. It is an image problem (PHP 8.5.0 ZTS in dev
  against 8.5.12 in CI), not of any one screen.
- Browser-driving scripts used for the verification were throwaway; the method is in the
  checklist of `composed-screens.md`.
- Commits go straight to `main` here (fetch and rebase first). nubit-react `main` is protected.
