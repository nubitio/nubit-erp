# Composed UI refactor: plan and status

Last updated 2026-11-20. Read with `docs/ui/composed-screens.md` (recipe and gotchas) and
`docs/adr/ADR-019-*.md` (decision), `ADR-020-*.md` (path routing).

## Where we are

| Item | State |
| --- | --- |
| ADR-019 composed UI (components in the ERP, no new package) | Accepted, amended |
| ADR-020 path routing instead of HashRouter | Done and verified (steps 1 to 3, e2e, identity flows) |
| Foundation: `assets/shared/ui/{data-grid,entity-form}`, `formErrors`, `downloadBlob` | Done, tested |
| Customers pilot (`/customer`) | Reverted in `6c1b8da94`; the route renders `CustomerPage` backed by `SchemaCrudPage` |
| POS unit counter on weighted lines (per-tenant setting) | Done and committed |
| Customer detail panel with receivables | Implemented in `87856782e`, then removed with the composed page in `6c1b8da94`; not available on `/customer` |
| Next waves | Catalog/Products, then Purchasing/AP/Payroll/Fixed assets, Sales last |

## Proposed follow-ups

1. **Revisit the customer detail panel with receivables.** Use `87856782e` as a reference if the
   panel is restored, and re-verify the API and permissions against the active Customers screen.
2. **nubit-react PR: dark-theme contrast.** The active segmented-control button (3.47:1) and the
   secondary button (4:1) fail WCAG AA in dark mode. Protected `main`: branch, PR, merge when CI
   is green.
3. **Catalog/Products wave**, following the recipe. The products form is the biggest win:
   variants, units and prices deserve a real layout instead of generated fields.
4. Keep the active Customers wiring. If a replacement ships, check all embedded consumers
   before removing any shared form or model.

## Open decisions for the owner

- Whether the customer panel ships in the pilot or with the Catalog wave.

## Known limits of what was verified

- The POS unit counter has unit and component tests but was never driven in a browser with a
  tenant that has the setting on (e2e has no weighted-product fixture).
- Save and delete were verified for the original composed Customers pilot, not for the restored
  schema-driven page. Re-run that kind of pass for every new screen.

## Local environment notes

- ERP e2e on a clean stack: `scripts/ci/e2e-local.sh` (isolated compose project `nubit-e2e`). Its
  OpenAPI compatibility step fails when `assets/api/types.generated.ts` is stale; regenerate it
  from `php bin/console api:openapi:export` and convert with `openapi-typescript`.
- If ports 3306 or 6379 are already in use, change the host port mappings in a local Compose
  override. Keep Redis reachable on the project network.
- The browser verification method is in the checklist of `composed-screens.md`.
