# ADR-019: Composed UI over schema-driven CRUD for business screens

Date: 2026-11-16
Status: Accepted, amended 2026-11-17

> **Amendment 2026-11-17 — no new package.** The first version of this ADR put the new
> components in a new published package (`@nubitio/views`) and justified it as shared with
> Inbox and Billing. That justification was wrong: the `@nubitio/*` consumers are
> `nubit-control`, `nubit-erp`, `nubit-erp-site` and `aqp-net`; Inbox and Billing do not use
> them. The owner also does not want to maintain more libraries (`nubit-react` already
> publishes nine packages). D2, D3 and the migration plan were rewritten accordingly: the
> components live in this repo (`assets/shared/ui`). The spike results in D4 stand.

## Context

The tenant SPA (`assets/`, ~92k LOC) renders most screens through
`@nubitio/crud` (`SchemaCrudPage` + `defineResource` + `defineFields`). The
engine resolves a Hydra/`x-crud` schema into a grid, a dialog/drawer/page form,
RBAC, filters, column presets, audit and print.

Measured on 2026-11-16:

| Signal | Value |
| --- | --- |
| Screens built on `SchemaCrudPage` | 40 of ~60 |
| `kind: 'override'` field directives | 214 |
| Inline cell formatters | 59 |
| Files wiring `FormLayout` / `FormHandle` / `createCrudEvents` | 49 |

Two populations hide inside the 40:

- **Flat CRUD** (categories, units, payment methods…): ~40 lines each, schema
  `augment`. The engine fits and stays.
- **Business screens** (`ProductPage` 486 LOC, `SalePage` 553, `StockItemPage`
  546, `CustomerPage` with `strategy: 'replace'`): the schema is a wrapper
  around hand-written UI expressed in a DSL — field patches, render props,
  `GridHandle`/`FormHandle` refs (`SalePage` needs an `eslint-disable
  react-hooks/refs`), and an event bus.

The engine owns the page layout. Putting KPIs, a timeline, a side panel,
contextual actions or a rich cell next to a record means fighting it. Control
is inverted (register callbacks, read refs) instead of composed (props, hooks).
Record types degrade to `unknown` (`category`, `baseUnit`, `stocks`), and logic
trapped in the engine is only testable end-to-end (57 unit tests vs. a large
Playwright suite).

`features/sales/detail/SaleDetailPage.tsx` already shows the alternative:
`Page`, `PageHeader`, `DetailLayout`, `Section`, `DescriptionList`, `DataTable`,
`Timeline` from `@nubitio/ui` over plain `useQuery`. The POS and the receivables
screens work the same way.

### What the libraries already provide (nubit-react @ v1.3.1)

Verified against `origin/main` of `nubitio/nubit-react`, not the ERP's
`node_modules`:

- `@nubitio/ui` — presentational primitives built on `react-aria-components`:
  `Combobox` (server-side `loadOptions`), `DataTable` (presentational: columns,
  rows, actions, pagination — **no** sorting, selection, resizing, saved views),
  `FilterPanel`, `DetailLayout`, `Tabs`, `Drawer`, date/time pickers,
  `Timeline`, `Skeleton`, `KpiMetricRow`. Zero heavy dependencies by design.
- `@nubitio/crud` — contains reusable **headless** pieces that are not tied to
  `SchemaCrudPage`: `useGridQueryState` (filters/operators/sort/page as one
  state machine), `useGridDataLoader`, `useGridSelection`, `useColumnChooser`,
  `useCursorPagination`, `useGridExport`, the `ResourceStore` seam with Hydra
  and REST adapters, the Field-Type registry (`cellText`, `CellRender`,
  `ControlRender`, serialization, validation), `usePermissions`, workflow,
  `AuditTrailPanel`, `PrintButton`, `DocumentHistoryPanel`.
- Backend (`nubit-symfony`): a versioned grid query contract
  (`x-grid-protocol` + fixtures), `x-crud`, `#[EmbeddedLines]`, `#[Workflow]`,
  `#[Printable]`, `#[Exportable]`, `#[Importable]`. The contract is what makes
  the UI replaceable without touching PHP.

## Decision

### D1 — Business screens are composed; flat CRUD stays schema-driven

`SchemaCrudPage` remains the right tool for Tier 1/2 resources
(`nubit-react/docs/architecture/tiers.md`) and is **not** migrated. Screens that
today need `replace`-strategy contracts, event wiring or ref access are rebuilt
as composed screens. No big-bang: migration is per screen (strangler), mirroring
ADR-001/006 on the backend.

### D2 — The components live in the ERP; no new package

Generic building blocks (`DataGrid`, `EntityForm`, `ListView`, `RecordView`) go in
`assets/shared/ui/`; feature-specific composites (`CustomerPicker`, `SunatStatusBadge`,
document totals, money/price cells) stay next to their feature. Nothing is added to
`nubit-react`, and nothing new is published, versioned or released.

The ERP gains two **direct** dependencies it already has transitively through
`@nubitio/ui`: `react-aria-components` (pinned to the version `ui` uses, so the bundle
carries one copy) and `react-hook-form`. `@nubitio/ui` keeps providing the visual
primitives and tokens.

Why not a package, and what would change the answer:

- Only the ERP needs these components today. `nubit-control` and `aqp-net` use the
  schema-driven engine; nothing says they want composed screens.
- Every component change in a package costs a cross-repo cycle (branch, PR on a protected
  `main`, release, version bump in the ERP). Inside the ERP it is one commit.
- **Promotion rule:** when a second app asks for the same component, move it to
  `@nubitio/ui` (it already depends on `react-aria-components`, so a grid adds no
  dependency; forms stay out of `ui` because of `react-hook-form`). Until a second consumer
  exists, extracting would be speculative.
- The cost is accepted: if that day comes, the extraction is real work. It is smaller than
  maintaining an unused library meanwhile.

### D3 — Explicit and typed; no runtime schema engine

Screens declare their columns and fields explicitly, typed from the generated OpenAPI
types (`assets/api/types.generated.ts`), and talk to the API through `httpClient` and
TanStack Query, as `SaleDetailPage` already does. The components do not resolve a Hydra
schema at runtime; that is precisely the layer being moved away from for business screens.

`@nubitio/crud`'s headless hooks (`useGridQueryState`, `useGridDataLoader`,
`useGridSelection`, `usePermissions`) are **not** reused, and none has to be exported from
`nubit-react`. The spike showed that the load-bearing invariants are small and are
implemented here instead:

- Narrowing the result set resets the page in the **same state transition** (never in an
  effect after the fact, which issues a request for the new filter on the old page).
- Requests carry the `AbortSignal` from TanStack Query, so a superseded request is
  cancelled, not merely ignored.
- Selection is by id and survives pagination, with a distinct "all rows matching the
  filter" mode (an `allMatching` flag plus an exclusion set).
- `ui/Pagination` is 1-based and the query is 0-based; the grid owns the conversion.
- While previous-page data is shown during a refetch, interaction with those rows is
  disabled.

Filter parameters follow what each endpoint accepts. Where a list needs the
`x-grid-protocol` tuple form, a thin builder mirrors its output and is checked against
`contracts/x-grid-protocol.fixtures.json` so the two cannot drift. Whether any pilot screen
needs it is decided when the Customers endpoint is read.

### D4 — Libraries (revised 2026-11-17 after a spike)

Original proposal was TanStack Table + React Hook Form + Zod. A spike
(`nubit-react/docs/architecture/views.md`, Appendix A: 17 browser tests, axe,
bundle measurements) changed two of the three:

- **Table: React Aria Components `Table`**, not TanStack Table. `ui` already
  depends on `react-aria-components`; its `Table` provides ARIA grid roles,
  keyboard navigation, sorting, selection and column resizing, and adds ≈ 6 KB
  gzip over what `ui` ships, whereas TanStack Table is headless (no
  accessibility) and a new 13 KB dependency. The server-driven query state,
  id-based selection, sticky columns (CSS) and column-state persistence are ours.
  Verified: server sort/filter/paging, request cancellation, selection across
  pages and "all matching", resize + persistence, sticky columns, row menu,
  keyboard, axe WCAG 2.1 AA clean, 100 rich rows in ~80–110 ms (dev machine).
- **Forms: React Hook Form**, **without** a mandatory schema library.
  Verified: works with `ui` controls (`register` for inputs, `Controller` for
  `Combobox`/`DatePicker`/`Toggle`), 0 line re-renders while typing in a
  30-line form vs 150 for a single-state controlled form, `useFieldArray` for
  document lines, and API Platform 422 mapping through the existing
  `mapApiViolations`. `EntityForm` accepts any RHF `Resolver`. Zod v4 costs
  ~95 KB gzip (`zod/mini` 5 KB, Valibot 1.4 KB) and is only a transitive
  dependency today, so it is not adopted by default.
- **Server state: TanStack Query stays.** 70 ERP files, 112 `useQuery`, 94
  `useMutation`, 36 invalidations; a peer of five `@nubitio` packages (the
  `admin` shell creates the `QueryClient`); ~11 KB gzip. Removing it would be a
  breaking change across the stack with no identified benefit.
- **Accessibility primitives: `react-aria-components`** (unchanged).

Open consequences found by the spike (details in views.md): `ui/Pagination` is
1-based while the query state is 0-based; resetting the page in an effect
double-fetches; with RAC's toggle selection, `Enter`/click stop opening a row
once something is selected, so the primary column should be a real link; and
`useBlocker` (dirty-form guard) needs a data router while the ERP uses
`<HashRouter>` — a router migration decision is pending.

### D5 — Typed data layer per resource

Each migrated resource gets typed hooks (`useCustomers`, `useCustomer`,
`useSaveCustomer`) over `httpClient` and the already-generated OpenAPI types
(`assets/api/types.generated.ts`). Record interfaces stop using `unknown` for
relations. Query keys follow the existing
`['<resource>', <scope>, branchId, …]` convention (see `SaleDetailPage`).

### D6 — Screen anatomy

Two page archetypes cover the ERP; both are compositions, not engines:

- **ListView**: `PageHeader` (title, primary action) → KPI strip (optional) →
  toolbar (search, filter chips, saved views, column chooser, density, export) →
  `DataGrid` (sort, resize, sticky columns, row selection, bulk actions, row
  menu, rich cells, empty/loading/error states) → pagination.
- **RecordView**: `PageHeader` (status badges, workflow actions, print) →
  `DetailLayout` (main: tabbed sections / lines grid; aside: summary, activity
  `Timeline`, related records). Create/edit use `EntityForm` in the same layout
  (page, drawer or dialog chosen by the caller, not by a config enum).

Document screens (sales, purchases, quotes) additionally need a **lines editor**
(keyboard-first editable grid with totals) — scoped as its own component, because
it is the hardest piece and is deferred to the last wave.

### D7 — Behaviour that must survive the migration

Per migrated screen: RBAC via `hasPermission` / `usePermissions`; branch-scoped
query keys; `x-printable` print/reissue flow; workflow transitions published by
`#[Workflow]`; audit trail panel; column-preset persistence; Excel export; empty
states. The existing Playwright specs for that screen are the acceptance
contract and must pass unchanged (selectors may be updated only with a
documented reason).

## Migration plan

0. **Prerequisites (no product change).** Add `react-aria-components` (same version as
   `@nubitio/ui`) and `react-hook-form` to the ERP. Nothing to prepare in `nubit-react`.
1. **No foundation phase.** Components are built **with** the pilot, not ahead of it:
   each is extracted into `assets/shared/ui/` when the Customers screen needs it, so
   nothing is designed speculatively. The contract notes in
   `nubit-react/docs/architecture/views.md` (query invariants, accessibility,
   keyboard) remain a useful checklist, but that document is archived: it described a
   package that will not exist.
2. **Pilot: Customers.** Small, but coupled to `SalePage`
   (`CustomerPage.EVENTS` / `API_URL`), which exercises the hard seam. Gate:
   `catalog-customer-forms`, `customer-document-lookup` e2e pass; D4 libraries
   confirmed or replaced here.
3. **Catalog & Products** (photo, per-warehouse stock, price lists, kardex tab).
4. **Purchasing, Accounts payable, Payroll, Fixed assets, Expense reports.**
5. **Sales** (`SalePage`, `InvoicePage`, `CreditNotePage`, receivables) last: the
   most coupled, fiscally critical path, and the one that needs the lines editor.
6. Remove `CustomerPage.EVENTS`-style static coupling and dead `crud` wiring
   from the ERP as each screen lands.

## Consequences

Positive:

- Layout and behaviour are the screen's own; richer UI no longer fights the
  engine.
- Typed end to end; logic lives in hooks and is unit-testable.
- No new library to publish, version or release; a component change is one commit.
- Backend and the `x-*` contracts do not change.

Negative / costs:

- Two ways to build a screen coexist for the duration of the migration, and
  flat CRUD keeps using the schema engine indefinitely.
- More code per screen than `defineResource`, in exchange for control. Mitigated
  by `ListView`/`RecordView`/`EntityForm` defaults and optional schema derivation.
- If `nubit-control`, `aqp-net` or `erp-site` later want the same components they
  cannot import them; the promotion rule in D2 covers it, at the price of an extraction.
- The ERP owns accessibility, keyboard behaviour and tests of its own grid and form
  (React Aria Components supplies the grid semantics; the layers above it are ours).

## Alternatives considered

1. **Keep `@nubitio/crud` and add layout slots/extension points.** Cheapest, but
   it grows the DSL further and keeps control inverted; the 214 overrides and
   ref-based wiring suggest the extension surface is already the problem.
2. **Build everything inside the ERP (`assets/shared/ui`).** **Chosen (amendment).**
   Fastest to iterate and nothing new to maintain. It does not duplicate what
   `nubit-react` ships: the primitives and tokens still come from `@nubitio/ui`.
3. **A new published package (`@nubitio/views`).** The first version of this ADR.
   Rejected: only one app would consume it, every change would cost a release cycle, and
   the owner does not want to maintain more libraries.
4. **Replace the stack with a third-party admin kit** (Refine, React-Admin,
   MUI/Ant). Imposes a second visual system and data model against the existing
   Nubit design system alignment (v1.2.0) and Hydra contract.
5. **Big-bang rewrite.** Rejected: ~92k LOC, fiscal correctness (SUNAT) on the
   sales path, and an e2e suite that is only meaningful if screens migrate one
   at a time.

## Validation

The spike (above) validated the library choices in isolation against a fake
backend. The pilot must re-validate them against the real Hydra `ResourceStore`
and the real ERP shell. It succeeds when, for Customers: e2e specs pass unchanged; the screen
gains at least one layout capability the engine could not express (e.g. side
panel with receivables summary and activity); the record type has no `unknown`
relations; and the form/grid logic has unit tests. If React Aria `Table` or React
Hook Form fit poorly under that load, revise D4 *before* wave 3. Not covered by
the spike: mobile/card layout, virtualisation beyond ~100 rows, real screen
readers, dark-theme contrast.

## Open questions

- When to promote a component to `@nubitio/ui` (D2): the trigger is a second consumer asking, not a date.
- Per-user saved views: stored server-side (needs a backend resource) or
  `localStorage` first?
- Lines editor: build in-house vs. a headless spreadsheet-style grid; decide
  when wave 5 is scoped, not now.
- Mobile/tablet scope for list and record views (the POS already has its own).
