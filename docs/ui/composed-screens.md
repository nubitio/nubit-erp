# Composed screens (ADR-019)

How to migrate a business screen from the schema-driven CRUD engine to a composed one, and what
was learned doing the first (Customers). Decision and rationale: `docs/adr/ADR-019-*.md`.

## Where things live

| Piece | Path | Rule |
| --- | --- | --- |
| Server-driven table | `assets/shared/ui/data-grid/` | `DataGrid` (React Aria `Table`), `useGridQuery`, `gridProtocol`, `gridState` |
| Entity form | `assets/shared/ui/entity-form/` | `Field`, `FormSection`, `EntityDrawer` |
| Server error mapping | `assets/shared/lib/formErrors.ts` | `applyServerErrors`: 422 violations to fields, 5xx to a generic message |
| One screen | `assets/features/<name>/` | `<Name>Page.tsx`, `<Name>Drawer.tsx`, `api/<name>Data.ts`, `form/<name>Form.ts` |

Components stay in the ERP. Promote one to `@nubitio/ui` only when a second app needs it
(no new package, ADR-019 amended).

## Recipe for a screen

1. **Data** (`api/`): a `use<Name>Grid` over `useGridQuery` (query key, search fields, filter
   mapping, initial sort), `useSave`/`useDelete` mutations that invalidate the list key, and an
   export function. Do not `await` the invalidation in `onSuccess`: the save is done, so the form
   must close without waiting for the list to reload.
2. **Form model** (`form/`): `toFormValues`, `toPayload` (blank optional field to `null`), the
   validators and their messages. Pure functions with tests.
3. **Drawer**: react-hook-form, one fresh form per opening (mount it keyed, do not reset in
   effects). Server errors go through `applyServerErrors`.
4. **Page**: header actions, toolbar (debounced search, filters), `DataGrid`, row actions with
   permissions, delete confirmation, mobile cards and a floating action button.
5. **Route**: point the route at the new page. Keep the old screen only while something else
   embeds it (Customers: `SalePage` still uses the old form).

## Verification checklist

- `yarn vitest run`, `tsc --noEmit`, `eslint`, `prettier --check`.
- e2e specs that touch the screen, on the clean CI-like stack (`scripts/ci/e2e-local.sh`). Adapt
  selectors from engine classes (`.nb-datagrid__*`) to roles and `data-testid`, and say why in
  the commit.
- **Drive it with Playwright against the real API**: create, edit, delete, a real 422, paging
  beyond one page, sort, every toolbar control, export, keyboard, and axe (WCAG 2.1 AA) in light
  and dark. Prefix test data (`QA-PW`) and delete it at the end, also on failure. This is what
  found the bugs below; unit tests did not.

## Gotchas

- **React Aria caches dynamic collection rows.** When the columns change (basic/full view) declare
  `dependencies` on `TableHeader`, `TableBody` and `Row`, or the table throws
  "Cell count must match column count". Covered by a test in `DataGrid.test.tsx`.
- `ui/Button` sets `aria-busy` itself from `loading`; passing your own `aria-busy`/`disabled` is
  overridden. Its `icon` takes the full class (`ph ph-x`); `EmptyState` takes the bare name.
- Error text must use `--nb-color-text-danger`. `--error-color` is a background red and fails
  contrast on dark surfaces.
- A 5xx `detail` can carry internals (host names, SQL). Never show it; `applyServerErrors` does
  not.
- `watch()` from react-hook-form trips the React Compiler lint; use `useWatch`.
- Playwright `getByLabel(..., { exact: true })` needs the label as a sibling of the control, and
  the required marker in CSS, so the accessible name is exactly the label.
- Pagination: the API is 1-based, `ui/Pagination` is 1-based, the grid state is 0-based. The
  conversion lives in `gridProtocol` and `DataGrid`; callers only see 0-based.
- Changing filters/search/sort/page size resets the page in the same reducer step, so there is
  never a request for a page that does not exist.

## Known gaps

- `@nubitio/ui` dark theme: the active segmented-control button (3.47:1) and the secondary
  button (4:1) miss WCAG AA. Fix in nubit-react, not here.
- Customer record side panel with receivables (the "new capability" that ADR-019 asks each pilot
  to prove) is not built yet.

## Order of the waves

Customers (done), Catalog/Products, Purchasing/AP/Payroll/Fixed assets, Sales last (it embeds
the most forms). Delete the engine wiring of a screen when its replacement ships.
