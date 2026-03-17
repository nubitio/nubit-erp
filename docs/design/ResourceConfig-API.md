# Design Document: `ResourceConfig` API — SLICE-003 / T003-1

> **Task:** T003-1 · **Spec:** SLICE-003 · **Author:** spex-architect  
> **Date:** 2026-03-17 · **Status:** ready-for-implementation

---

## 1. Context and Goals

The current CRUD pattern requires four boilerplate files per resource
(`model/events.ts`, `pages/*Fields.ts`, `pages/*Page.tsx`, route entry).
For a simple resource like `CategoryPage` this means 30+ lines of near-identical
wiring that must be copy-pasted and edited for every new entity.

The goal of this layer is a **single-object declaration** that drives the full
CRUD page without touching DxDataGrid / DxDialog / DxForm internals:

```tsx
export const ProductResource = defineResource({ … });
export const ProductPage = () => <CrudPage resource={ProductResource} />;
```

**Design constraints (derived from source reading):**

| Component | Key interface |
|---|---|
| `DxDataGrid` | `DxDataGridOptions` — requires `id`, `title`, `url`, `fields`, `events?: DataGridEventNames`, plus optional `sort`, `filter`, `allowAdd/Edit/Delete/Export`, `stateStoringEnabled`, `paging`, `mode`, `toolbar`, `detailUrl`, `detailFields` |
| `DxDialog` | `DxDialogOptions` — requires `events?: DialogEventNames`; optional `width`, `height` |
| `DxForm` | `DxFormOptions` — requires `url`, `fields`, `events?: FormEventNames`; optional `detailUrl`, `detailFields`, `detailPropertyName`, `format`, callbacks `onSaveSuccess`, `onSaveError`, `onDeleteSuccess`, `onDeleteError` |
| `createCrudEvents` | `(prefix) => { ADD, EDIT, DELETE, SAVE, CANCEL, SUCCESS, LOADING }` (all `as const`) |
| `Field` | Fully defined in `@core/field/Field.ts` |
| `DxFilterRule` | Defined in `@core/common/FilterRule.ts` |
| `ToolbarButtonItem` | Defined in `@core/event/EventTypes.ts` |

---

## 2. File Layout

```
assets/core/crud/
├── ResourceConfig.ts     ← interfaces (ResourceConfig)
├── defineResource.ts     ← factory + type helper
├── useCrudPage.ts        ← hook that wires events + fields + formRef
├── CrudPage.tsx          ← generic page component
└── index.ts              ← barrel export
```

---

## 3. TypeScript Interfaces — `ResourceConfig.ts`

```typescript
// assets/core/crud/ResourceConfig.ts

import { Field } from '@core/field/Field';
import { DxFilterRule } from '@core/common/FilterRule';
import { ToolbarButtonItem } from '@core/event/EventTypes';
import { DxFormEvents } from '@core/form/DxForm';
import { RefObject } from 'react';

/**
 * Sort descriptor — mirrors the shape DxDataGrid passes to its DataStore.
 */
export interface SortDescriptor {
  selector: string;
  desc: boolean;
}

/**
 * Optional factory for fields that need access to the form ref
 * (e.g. CustomerFields which calls formRef.current?.setFieldValue).
 *
 * When `fields` is a function, `useCrudPage` will call it with the formRef
 * it creates internally, giving the field definitions full access to the form.
 */
export type FieldsFactory = (formRef: RefObject<DxFormEvents | null>) => Field[];

/**
 * Full configuration object that describes a CRUD resource.
 *
 * Every property that has a comment "default: X" is not required —
 * CrudPage and useCrudPage will supply that default transparently.
 */
export interface ResourceConfig {
  // ─── Identity ─────────────────────────────────────────────────────────────

  /**
   * Unique resource identifier.
   * Used as the event-bus namespace prefix (`createCrudEvents(id)`)
   * and as the DataGrid `stateStoring` storage key (`datagrid-${id}`).
   */
  id: string;

  /** Human-readable title shown in the DataGrid toolbar header. */
  title: string;

  /** Base REST endpoint — used for both DataGrid dataSource and DxForm submit. */
  apiUrl: string;

  // ─── Fields ───────────────────────────────────────────────────────────────

  /**
   * Field array (or factory receiving formRef) for the main form and grid columns.
   *
   * Use a `FieldsFactory` (function) when field definitions need a back-reference
   * to the DxForm imperative API (e.g. to call setFieldValue from a button click).
   * `useCrudPage` will create the formRef first, then call the factory.
   *
   * Example (static):
   *   fields: [identityField().build(), textField().name('name').label('Nombre').build()]
   *
   * Example (dynamic / formRef-dependent):
   *   fields: (formRef) => CustomerFields(formRef)
   */
  fields: Field[] | FieldsFactory;

  // ─── Master-Detail (optional) ─────────────────────────────────────────────

  /**
   * Detail fields for inline DataGrid inside the form (master-detail pattern).
   * Maps to `DxForm.detailFields`.
   */
  detailFields?: Field[];

  /**
   * URL template for the detail endpoint.
   * Use `{id}` as a placeholder for the parent record id.
   * Example: `Constants.API_BASE_URL + 'invoice/{id}/items'`
   * Maps to both `DxDataGrid.detailUrl` and `DxForm.detailUrl`.
   */
  detailUrl?: string;

  /**
   * Property name in the payload that contains the detail items array.
   * Defaults to `'items'` inside DxForm — override here to match the API response.
   * Maps to `DxForm.detailPropertyName`.
   */
  detailPropertyName?: string;

  // ─── Grid behaviour ───────────────────────────────────────────────────────

  /** Default sort order passed to the DataGrid DataStore. */
  sort?: SortDescriptor[];

  /** Default filter applied to the DataGrid DataStore on mount. */
  filter?: DxFilterRule[];

  /**
   * Grid render mode.
   * - `'normal'` — standard mode with header + toolbar (default).
   * - `'minimal'` — hides toolbar; used for embedded grids.
   */
  mode?: 'normal' | 'minimal';

  /**
   * Enable server-side paging (DevExtreme `remoteOperations`).
   * default: true
   */
  paging?: boolean;

  /**
   * Persist column state (order, widths, visibility) to localStorage.
   * default: false
   */
  stateStoring?: boolean;

  // ─── CRUD permissions ─────────────────────────────────────────────────────

  /** Show the "Nuevo" toolbar button. default: true */
  allowAdd?: boolean;

  /** Show the "Editar" toolbar button. default: true */
  allowEdit?: boolean;

  /** Show the "Eliminar" toolbar button. default: true */
  allowDelete?: boolean;

  /**
   * Enable PDF/XLSX export buttons.
   * default: false (export is opt-in; most CRUD pages don't need it)
   */
  allowExport?: boolean;

  // ─── Dialog (popup) ───────────────────────────────────────────────────────

  /**
   * Width of the DxDialog popup in pixels.
   * Falls back to DxDialog's own default (480px) when omitted.
   */
  dialogWidth?: number;

  /**
   * Height of the DxDialog popup.
   * Falls back to DxDialog's own default (`'auto'`) when omitted.
   */
  dialogHeight?: number;

  // ─── Form ─────────────────────────────────────────────────────────────────

  /**
   * HTTP body serialisation format for DxForm submit.
   * - `'json'`      — standard JSON (default).
   * - `'multipart'` — multipart/form-data; required when the form has FILE fields.
   */
  format?: 'json' | 'multipart';

  // ─── Toolbar extras ───────────────────────────────────────────────────────

  /**
   * Extra toolbar buttons rendered after the built-in Add / Edit / Delete trio.
   *
   * Two shapes are accepted (matching DxDataGrid's `toolbar` prop):
   * - Flat array     → all buttons placed in a single group.
   * - Array of arrays → each inner array is treated as a separated group.
   */
  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];

  // ─── Lifecycle callbacks ──────────────────────────────────────────────────

  /**
   * Called after a successful POST or PATCH.
   * The grid auto-refreshes via the SUCCESS event regardless; this callback
   * is for side effects like showing a custom toast or updating local state.
   */
  onSaveSuccess?: (response: any) => void;

  /** Called when POST or PATCH fails (after HTTP error). */
  onSaveError?: (error?: any) => void;

  /**
   * Called after a successful DELETE.
   * The grid auto-refreshes via the SUCCESS event regardless.
   */
  onDeleteSuccess?: (response: any) => void;

  /** Called when DELETE fails. */
  onDeleteError?: (error?: any) => void;
}
```

---

## 4. Factory — `defineResource.ts`

```typescript
// assets/core/crud/defineResource.ts

import { ResourceConfig } from './ResourceConfig';

/**
 * Identity factory that acts as a type helper and optional future hook point.
 *
 * Usage:
 *   export const ProductResource = defineResource({
 *     id: 'product',
 *     title: 'Productos',
 *     apiUrl: Constants.API_BASE_URL + 'products',
 *     fields: ProductFields(),
 *     sort: [{ selector: 'name', desc: false }],
 *   });
 *
 * Why not just `const ProductResource: ResourceConfig = { … }`?
 * - Enables IDE autocompletion with no import of ResourceConfig by the caller.
 * - Provides a single interception point if defaults or validation are added later
 *   (e.g. warn when `id` contains spaces, or auto-append trailing slash to apiUrl).
 * - Signals intent — "this object is a resource declaration", not an arbitrary shape.
 */
export function defineResource(config: ResourceConfig): ResourceConfig {
  return config;
}
```

---

## 5. Hook — `useCrudPage.ts`

```typescript
// assets/core/crud/useCrudPage.ts

import { useMemo, useRef } from 'react';
import { createCrudEvents } from '@features/movement';
import { DxFormEvents } from '@core/form/DxForm';
import { DataGridEventNames, DialogEventNames, FormEventNames } from '@core/event/EventTypes';
import { Field } from '@core/field/Field';
import { ResourceConfig } from './ResourceConfig';

/**
 * The wiring bundle returned by useCrudPage.
 *
 * - `events`   — namespaced event strings ready to pass to DxDataGrid,
 *                DxDialog, and DxForm.
 * - `fields`   — memoised Field[] for the main form / grid columns.
 * - `formRef`  — imperative ref to DxForm; pass to `<DxForm ref={formRef} …>`.
 *                Needed when fields use the form API (e.g. setFieldValue).
 *                Also available for parent page custom logic (see §8 Escape hatches).
 */
export interface CrudPageWiring {
  events: DataGridEventNames & DialogEventNames & FormEventNames;
  fields: Field[];
  formRef: React.RefObject<DxFormEvents | null>;
}

/**
 * Encapsulates the boilerplate wiring every CRUD page repeats:
 *   1. createCrudEvents(id)       — event name namespace
 *   2. useMemo(() => fields, [])  — stable field array
 *   3. useRef<DxFormEvents>()     — imperative form handle
 *
 * The hook is intentionally thin — it does NOT subscribe to events or
 * interact with the DOM. Components remain responsible for rendering.
 */
export function useCrudPage(resource: ResourceConfig): CrudPageWiring {
  // 1. Stable event names — createCrudEvents is pure, but wrap in useMemo
  //    so object identity is stable across re-renders (prevents child churn).
  const events = useMemo(
    () => createCrudEvents(resource.id),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [resource.id],
  );

  // 2. Imperative DxForm handle — must be created before fields in case
  //    fields is a FieldsFactory that closes over formRef.
  const formRef = useRef<DxFormEvents | null>(null);

  // 3. Stable field array
  const fields = useMemo(
    () =>
      typeof resource.fields === 'function'
        ? resource.fields(formRef)
        : resource.fields,
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [resource.id], // re-derive only when the resource changes identity
  );

  return { events, fields, formRef };
}
```

---

## 6. Component — `CrudPage.tsx`

```tsx
// assets/core/crud/CrudPage.tsx

import { DxDataGrid } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { ResourceConfig } from './ResourceConfig';
import { useCrudPage } from './useCrudPage';

export interface CrudPageProps {
  resource: ResourceConfig;
}

/**
 * Generic CRUD page that wires together DxDataGrid + DxDialog + DxForm
 * from a single ResourceConfig declaration.
 *
 * Covers the standard pattern used by ~25 pages in the codebase.
 * Pages that need custom behaviour (custom toolbar, formRef callbacks,
 * multi-grid layouts, etc.) should use `useCrudPage` directly instead.
 */
export const CrudPage = ({ resource }: CrudPageProps) => {
  const { events, fields, formRef } = useCrudPage(resource);

  return (
    <div id="wrapper">
      <DxDataGrid
        id={resource.id}
        title={resource.title}
        url={resource.apiUrl}
        fields={fields}
        events={events}
        detailUrl={resource.detailUrl}
        detailFields={resource.detailFields}
        sort={resource.sort}
        filter={resource.filter}
        allowAdd={resource.allowAdd ?? true}
        allowEdit={resource.allowEdit ?? true}
        allowDelete={resource.allowDelete ?? true}
        allowExport={resource.allowExport ?? false}
        mode={resource.mode ?? 'normal'}
        paging={resource.paging ?? true}
        stateStoringEnabled={resource.stateStoring ?? false}
        toolbar={resource.toolbar}
      />
      <DxDialog
        events={events}
        width={resource.dialogWidth}
        height={resource.dialogHeight}
      >
        <DxForm
          ref={formRef}
          url={resource.apiUrl}
          fields={fields}
          events={events}
          detailUrl={resource.detailUrl}
          detailFields={resource.detailFields}
          detailPropertyName={resource.detailPropertyName}
          format={resource.format}
          onSaveSuccess={resource.onSaveSuccess}
          onSaveError={resource.onSaveError}
          onDeleteSuccess={resource.onDeleteSuccess}
          onDeleteError={resource.onDeleteError}
        />
      </DxDialog>
    </div>
  );
};

CrudPage.displayName = 'CrudPage';
```

---

## 7. Barrel Export — `index.ts`

```typescript
// assets/core/crud/index.ts

export type { ResourceConfig, SortDescriptor, FieldsFactory } from './ResourceConfig';
export { defineResource } from './defineResource';
export type { CrudPageWiring } from './useCrudPage';
export { useCrudPage } from './useCrudPage';
export type { CrudPageProps } from './CrudPage';
export { CrudPage } from './CrudPage';
```

---

## 8. Usage Examples

### 8.1 Simple resource — `ProductPage`

```typescript
// assets/features/catalog/pages/ProductResource.ts
import { defineResource } from '@core/crud';
import { Constants } from '@core/common/Constants';
import { ProductFields } from './ProductFields';

export const ProductResource = defineResource({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: ProductFields(),
  sort: [{ selector: 'name', desc: false }],
  allowAdd: true,
  allowEdit: true,
  allowDelete: true,
});
```

```tsx
// assets/features/catalog/pages/ProductPage.tsx  (new version)
import { CrudPage } from '@core/crud';
import { ProductResource } from './ProductResource';

export const ProductPage = () => <CrudPage resource={ProductResource} />;
```

**Reduction:** 34 lines → 5 lines (85% less boilerplate).

---

### 8.2 Resource with formRef dependency — `CustomerPage`

`CustomerFields` receives a `formRef` so the RUC search button can call
`formRef.current?.setFieldValue(…)`. Use the `FieldsFactory` signature:

```typescript
// assets/features/catalog/pages/CustomerResource.ts
import { defineResource } from '@core/crud';
import { customerApiUrl } from '@features/customer';
import { CustomerFields } from './CustomerFields';

export const CustomerResource = defineResource({
  id: 'customer',
  title: 'Clientes',
  apiUrl: customerApiUrl,
  // fields as a FieldsFactory — receives formRef from useCrudPage
  fields: (formRef) => CustomerFields(formRef),
});
```

```tsx
// assets/features/catalog/pages/CustomerPage.tsx  (new version)
import { CrudPage } from '@core/crud';
import { CustomerResource } from './CustomerResource';

export const CustomerPage = () => <CrudPage resource={CustomerResource} />;
```

---

### 8.3 Simple resource — `CategoryPage`

```typescript
// assets/features/catalog/pages/CategoryResource.ts
import { defineResource } from '@core/crud';
import { categoryEndpoints } from '../api/catalogEndpoints';
import { CategoryFields } from './CategoryFields';

export const CategoryResource = defineResource({
  id: 'category',
  title: 'Categorias',
  apiUrl: categoryEndpoints.list(),
  fields: CategoryFields(),
});
```

```tsx
// assets/features/catalog/pages/CategoryPage.tsx  (new version)
import { CrudPage } from '@core/crud';
import { CategoryResource } from './CategoryResource';

export const CategoryPage = () => <CrudPage resource={CategoryResource} />;
```

---

### 8.4 Resource with master-detail — hypothetical `InvoicePage`

```typescript
export const InvoiceResource = defineResource({
  id: 'invoice',
  title: 'Facturas',
  apiUrl: Constants.API_BASE_URL + 'invoices',
  fields: InvoiceFields(),
  detailFields: InvoiceItemFields(),
  detailUrl: Constants.API_BASE_URL + 'invoices/{id}/items',
  detailPropertyName: 'items',
  format: 'json',
  dialogWidth: 900,
  allowExport: true,
  onSaveSuccess: (res) => console.log('Saved invoice', res.id),
});
```

---

### 8.5 Resource with extra toolbar buttons

```typescript
export const ProductResource = defineResource({
  id: 'product',
  title: 'Productos',
  apiUrl: Constants.API_BASE_URL + 'products',
  fields: ProductFields(),
  sort: [{ selector: 'name', desc: false }],
  toolbar: [
    {
      text: 'Importar',
      icon: 'ph ph-upload',
      type: 'normal',
      onClick: () => {/* open import dialog */},
    },
  ],
});
```

---

## 9. Escape Hatches — Custom Logic Patterns

Pages with logic that cannot be expressed purely through `ResourceConfig` must
**not** be shoehorned into `CrudPage`. The escape is to use `useCrudPage`
directly and compose manually — keeping the wiring overhead minimal:

### Pattern A — Accessing `formRef` in the page body

```tsx
export const CustomerPage = () => {
  const { events, fields, formRef } = useCrudPage(CustomerResource);

  // Custom: listen to SUCCESS to redirect somewhere
  const [on] = useEvents();
  useEffect(() => {
    on(events.SUCCESS, () => navigate('/dashboard'));
  }, []);

  return (
    <div id="wrapper">
      <DxDataGrid id="customer" title="Clientes" url={CustomerResource.apiUrl}
        fields={fields} events={events} allowAdd allowEdit allowDelete />
      <DxDialog events={events}>
        <DxForm ref={formRef} url={CustomerResource.apiUrl}
          fields={fields} events={events} />
      </DxDialog>
    </div>
  );
};
```

### Pattern B — Multiple grids / non-standard layout

Use `useCrudPage` for each resource and compose freely — the hook does nothing
more than create stable events + fields + formRef.

### Pattern C — Keeping the old pattern entirely

Pages that have complex custom logic (e.g. `SaleFormPage`, `InboundPage`) that
bear no resemblance to a single-grid CRUD page **do not need to migrate**. The
new API is additive — existing pages continue to work unchanged.

---

## 10. Compatibility Notes

### 10.1 Event modules stay in `features/`

`defineResource` does **not** absorb the `features/*/model/events.ts` module.
`createCrudEvents` is called *inside* `useCrudPage`, so the generated events
are identical. The `productEvents`, `customerEvents` etc. module-level exports
remain valid and unchanged for pages that have not yet migrated.

### 10.2 `CustomerPage.EVENTS` / `CustomerPage.API_URL` static members

`CustomerPage.tsx` currently exposes two static members used by other pages
(e.g. for cross-page event broadcasting):
```ts
CustomerPage.EVENTS  = customerEvents;
CustomerPage.API_URL = customerApiUrl;
```
During migration, keep these by re-exporting the resource values:
```ts
export const CustomerPage = () => <CrudPage resource={CustomerResource} />;
CustomerPage.EVENTS  = CustomerResource.id; // caller rebuilds if needed
CustomerPage.API_URL = CustomerResource.apiUrl;
```
Or, better: export from the resource module directly and update call sites.

### 10.3 `DxDataGrid` `mode` prop discrepancy

`DxDataGridOptions.mode` accepts `'minimal' | 'normal' | 'full'`. `ResourceConfig`
maps to **`'normal' | 'minimal'`** since `'full'` is DevExtreme-internal and
the codebase uses `'full'` as the default inside `DxDataGrid` itself when the
prop is omitted. `CrudPage` omits `mode` entirely when `resource.mode` is `undefined`
so the DxDataGrid default (`'full'`) applies naturally.

**Update:** `CrudPage.tsx` line:
```tsx
mode={resource.mode ?? 'normal'}
```
Should become:
```tsx
mode={resource.mode}   // omit the prop; let DxDataGrid default to 'full'
```
The implementor should choose which value to pass. This document recommends
**omitting** to preserve the existing DxDataGrid default.

### 10.4 `fields` passed to both `DxDataGrid` and `DxForm`

Both components receive the **same** `fields` array reference (stable via `useMemo`).
This mirrors the existing pattern exactly — no change in behaviour.

### 10.5 TypeScript strict mode

`formRef` is typed `RefObject<DxFormEvents | null>` (not `MutableRefObject`)
because `useRef(null)` returns `RefObject` in React 18 typings.
`DxForm`'s `ref` prop accepts `React.Ref<DxFormEvents>` via `forwardRef`, which
is compatible with `RefObject<DxFormEvents | null>`.

---

## 11. Testing Guidance (for T003-4)

| Test | What to assert |
|---|---|
| `defineResource` identity | `defineResource(cfg) === cfg` is `false` (different reference) but deeply equal |
| `useCrudPage` events stable | Re-render with same resource → `events` object reference unchanged |
| `useCrudPage` fields static | `fields` is the same reference across renders when `resource.id` unchanged |
| `useCrudPage` FieldsFactory | When `fields` is a function, it receives the `formRef` created by the hook |
| `CrudPage` snapshot | Renders `DxDataGrid + DxDialog + DxForm` with correct props from resource |
| `CrudPage` defaults | `allowAdd/Edit/Delete` default to `true` even when not in resource config |
| `CrudPage` detailFields | When `detailFields` present, DxForm receives them |

---

## 12. File Summary

| File | Purpose | ~Lines |
|---|---|---|
| `assets/core/crud/ResourceConfig.ts` | Interface + supporting types | ~120 |
| `assets/core/crud/defineResource.ts` | Identity factory | ~15 |
| `assets/core/crud/useCrudPage.ts` | Wiring hook | ~40 |
| `assets/core/crud/CrudPage.tsx` | Generic page component | ~45 |
| `assets/core/crud/index.ts` | Barrel export | ~10 |
| **Total** | | **~230** |

---

*Document end — T003-1 complete. Implementor: T003-2 → spex-frontend.*
