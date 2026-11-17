# ADR-019: Composed UI over schema-driven CRUD for business screens

Date: 2026-11-16
Status: Proposed

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

### D2 — New components live in nubit-react, not in the ERP

The `@nubitio/*` packages are the shared design system and engine for every
Nubit product (ERP, Inbox, Billing). Generic building blocks belong there so
they are reusable, versioned and tested once. ERP-specific composites
(`CustomerPicker`, `SunatStatusBadge`, document totals, money/price cells) stay
in `assets/shared/ui/`.

Placement inside nubit-react:

- **`@nubitio/ui`** keeps its "no heavy dependencies" rule. It receives only
  presentational additions that need none (layout slots, `Toolbar`, `BulkBar`,
  `SavedViews` shell, density tokens, `StatusBadge` variants).
- **A new package** (working name `@nubitio/views`) holds the stateful,
  dependency-bearing layer: `DataGrid`, `ListView`, `RecordView`, `EntityForm`.
  It depends on `ui`, `core` and the headless parts of `crud`; it never imports
  `hydra` (same boundary rule `check-package-boundaries.mjs` enforces for
  `crud`).

### D3 — Schema-aware, not schema-bound

The new components take **explicit column and field definitions** as the primary
API, and may *optionally* derive defaults from the Hydra schema through the
existing Field-Type registry (`useResourceSchema` → `Field[]` → column/control
defaults). This keeps the one thing the engine does well — never re-declaring
what the backend already states (types, required, enums, permissions, workflow
transitions) — while handing layout and behaviour to the caller.

The grid reuses `useGridQueryState`, `buildFilterExpression`, `useColumnChooser`
and the `ResourceStore`, so the server-side filter/sort/page semantics and the
`x-grid-protocol` fixtures stay the single source of truth. It is not a second
implementation of the query logic.

Correction after reading nubit-react v1.3.1 (2026-11-16): `useGridQueryState`,
`buildFilterExpression`, `usePermissions` and `useGridExport` are **not exported**
from `@nubitio/crud` today and must be exported first. `useGridDataLoader`
(manual `useState`/`useEffect` fetching) and `useGridSelection` (array-of-keys,
O(n²)) are deliberately **not** reused: the new grid loads through TanStack Query
over `ResourceStore.load` and uses an id-based selection model that survives
pagination. Details in `nubit-react/docs/architecture/views.md` §2.

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

0. **Prerequisites (no product change).**
   - Sync nubit-react: the local checkout is 55 commits behind `origin/main`
     (v0.11.2 vs v1.3.1) and has uncommitted work in `NativeFormView` and core
     translations that belongs to someone's in-flight change. Resolve that
     before branching new work there.
   - Pin the ERP and nubit-react to the same published version line.
   - Component contracts (props, states, a11y, keyboard) are written in
     nubit-react `docs/architecture/views.md` (drafted 2026-11-16) and reviewed
     before implementing.
   - Export the headless symbols `views` needs from `@nubitio/crud` (see
     views.md §2).
1. **Foundation in nubit-react**: `DataGrid`, `ListView`, `EntityForm`,
   `RecordView`, density and layout tokens, Storybook-style demo in
   `examples/admin-demo`, unit + interaction tests, `check` pipeline green.
   Publish as a pre-release; ERP consumes it via the registry, not a link.
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
- Components are shared across Nubit products and versioned once.
- Backend and the `x-*` contracts do not change.

Negative / costs:

- Two ways to build a screen coexist for the duration of the migration, and
  flat CRUD keeps using the schema engine indefinitely.
- More code per screen than `defineResource`, in exchange for control. Mitigated
  by `ListView`/`RecordView`/`EntityForm` defaults and optional schema derivation.
- Cross-repo coordination (nubit-react release → ERP bump) on every component
  change; mitigated by pre-releases and the shared version line.
- A new package to own, document and version.

## Alternatives considered

1. **Keep `@nubitio/crud` and add layout slots/extension points.** Cheapest, but
   it grows the DSL further and keeps control inverted; the 214 overrides and
   ref-based wiring suggest the extension surface is already the problem.
2. **Build everything inside the ERP (`assets/shared/ui`).** Fastest to iterate,
   but forks the design system from Inbox/Billing and duplicates what nubit-react
   already ships.
3. **Replace the stack with a third-party admin kit** (Refine, React-Admin,
   MUI/Ant). Imposes a second visual system and data model against the existing
   Nubit design system alignment (v1.2.0) and Hydra contract.
4. **Big-bang rewrite.** Rejected: ~92k LOC, fiscal correctness (SUNAT) on the
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

- Package name and whether `views` is one package or split (`grid`, `form`).
- Per-user saved views: stored server-side (needs a backend resource) or
  `localStorage` first?
- Lines editor: build in-house vs. a headless spreadsheet-style grid; decide
  when wave 5 is scoped, not now.
- Mobile/tablet scope for list and record views (the POS already has its own).
