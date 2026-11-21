# `@nubitio` component catalog

Cache of `@nubitio/*` 2.1.0. If a prop here is missing from the installed types, the types win:
`node_modules/@nubitio/ui/dist/index.d.mts`. List every ui export again with:

```bash
grep -E "^export (declare )?(function|const|class)" node_modules/@nubitio/ui/dist/index.d.mts \
  | sed -E 's/export (declare )?(function|const|class) ([A-Za-z0-9_]+).*/\1\3/'
```

All imports: `import { … } from '@nubitio/ui'`. Styles load once at the app root.

## Actions

| Component | Key props | Use for / avoid |
| --- | --- | --- |
| `Button` | `variant: primary｜secondary｜success｜danger｜ghost`, `size: sm｜md`, `icon` ("ph ph-…"), `loading`, `fullWidth`, native button props | Any action. One `primary` per view. No `lg`, no `link` variant. |
| `ButtonLink` | same visual props, `as` (e.g. router `Link`), `disabled`, the element's own props (`to`, `href`) | Navigation that looks like a button. Not for actions. |
| `IconButton` | `icon`, `label` (required, accessible name), `variant: default｜danger` | Toolbar and row icon-only actions. |
| `QuantityStepper` | `value`, `onChange`, `label`, `min`, `max`, `step`, `unit`, `format`, `disabled` | +/- quantity (cart lines). Clamps to `[min,max]`. |

## Overlays

| Component | Key props | Notes |
| --- | --- | --- |
| `AppDialog` | `open`/`visible`, `title`, `onClose`, `children`, `footer`, `width`, `maxWidth`, `fullScreen`, `showCloseButton`, `closeOnOutsideClick`, `closeOnEscape`, `initialFocus` (ref), `keepMounted`, `*ClassName` | Actions go in `footer` (right-aligned). Modal, focus-trapped. |
| `ConfirmDialog` | `open`, `title`, `message`, `onConfirm`, `onCancel`, `confirmLabel`, `cancelLabel`, `variant: danger｜warning｜info` | Controlled confirmation. |
| `useConfirm` | `() => { ask({title,message,variant,confirmLabel,onConfirm}), dismiss, ConfirmHost }` | Render `<ConfirmHost />` once, call `ask` from handlers. Preferred for deletes. |
| `Drawer` | `isOpen`, `onClose`, `title`, `width`, `side`, `scrim`, `busy`, `footer`, `children` | Side panel for record edit/detail. `busy` blocks dismissal. |
| `Popover` | `trigger({open,toggle})`, `panel({close})`, `align`, `ariaLabel` | Anchored panel. |
| `ContextMenu` | `items: {label,icon,danger,disabled,onClick,separator}[]`, `trigger`, `triggerLabel`, `align`, `visible` | Overflow menu (⋯). |
| `RowActions` | `items`, `triggerLabel`, `align` | `ContextMenu` preset for table rows. |
| `Tooltip` / `TooltipProvider` | `content`, `side`, `delay`, `disabled`; child must forward ref | Supplementary text only. |
| `useFloatingPanel`, `useOverlayContainer` | | Custom portalled panels; portal into `useOverlayContainer()` inside modals. |

## Form controls

| Component | Key props | Notes |
| --- | --- | --- |
| `FormField` | `label`, `error`, `helpText`, `required` (visual), `htmlFor`, `children` | Wraps one control; error replaces help. |
| `TextField` | native input props, `invalid` | Forwards ref. `type="number"`, `inputMode`, `maxLength` as native. |
| `TextAreaField` | native textarea props, `invalid` | |
| `SelectField` | native select props, `invalid` | Native `<select>`. |
| `Combobox` | `options: {value,label,disabled,content}[]`, `value: string｜null`, `onChange`, `label`, `error`, `helpText`, `clearable`, `loadOptions(query,{signal,cursor})`, `minSearchLength`, `debounceMs` | Single select with filter; remote search via `loadOptions` (seed `options` with the selected record). |
| `MultiSelect` | like `Combobox`, `value: string[]` | Chips. |
| `AppDropdown` | `value`, `options`, `onChange`, `variant: form｜toolbar｜compact`, `label`, `error`, `helpText` | Non-searchable select. |
| `SearchableAppDropdown` | `AppDropdown` + `onSearch`, `searchable`, `selectedOption` | Compat adapter; prefer `Combobox`. |
| `Checkbox` | native props, `label`, `indeterminate`, `invalid` | |
| `RadioGroup` | `options`, `value`, `onChange`, `label`, `name`, `orientation` | |
| `Toggle` | `checked`, `onChange(checked)`, `label`, `size`, `aria-label` | Controlled switch. |
| `SegmentedControl` | `options`, `value`, `onChange`, `ariaLabel`, `size` | 2–4 exclusive choices. |
| `DatePicker` | `value` (YYYY-MM-DD), `onChange`, `min`, `max`, `locale="es-PE"`, `invalid`, `ariaLabel` | |
| `DateRangePicker` | `startValue`, `endValue`, `onChange(start,end)` | |
| `TimePicker` | `value` (HH:mm), `onChange`, `hourCycle`, `min`, `max` | |
| `FileDropzone` | `onFileSelect`, `onClear`, `value`, `accept`, `image`, `uploading`, `error` | |

## Layout and structure

| Component | Key props | Notes |
| --- | --- | --- |
| `Page` | `fill`, `wide`, `narrow`, `gap` | Page container. |
| `PageHeader` | `title`, `subtitle`, `metadata`, `breadcrumb`, `badge`, `actions` | Primary page action goes in `actions`. |
| `Section` | `title`, `variant: label｜panel` | |
| `Card` | `title`, `description`, `variant: auth｜panel` | |
| `Stack` / `Row` / `Cluster` | `gap` (1–10), `align`, `justify`, `wrap`, `fill` | Vertical / horizontal / wrapping-button layout. |
| `Grid` / `Col` | `cols: 1｜2｜3｜4｜12｜'auto'`, `gap`, `minColWidth`; `Col span/spanSm/spanMd/spanLg/fullWidth` | |
| `FormLayout` | `variant: stack｜grid｜inline｜filter`, `minColWidth`, form props | The `<form>`. |
| `FormActions` | `children` | Inline action row **inside the body** (left-aligned). Not for dialog footers. |
| `DetailLayout` | `aside`, `asideLabel`, `children` | Record detail main/aside. |
| `CollapsibleSection` | `label`, `open`, `onToggle`, `icon`, `trailing` | Controlled. |
| `Tabs` | `items: {value,label,content}[]`, `value`, `onChange`, `aria-label`, `keepMounted` | |
| `ScopeTabs` | `options`, `selectedKey`, `onChange`, `ariaLabel`, `allLabel` | Hides itself with ≤1 option. |
| `AppToolbar` | `title`, `icon`, `showRefreshButton`, `onRefresh`, `additionalToolbarContent` | |
| `HubPanel`, `OperationCardGrid`, `FilterPanel` | | Feature-hub chrome above a grid. |

## Data display and feedback

| Component | Key props | Notes |
| --- | --- | --- |
| `DataTable` | `columns: {id,header,align,render,accessor}[]`, `rows`, `getRowKey`, `rowActions`, `pagination`, `emptyMessage`, `variant` | Small static tables. Server-driven lists use the ERP `DataGrid` or `SchemaCrudPage`. |
| `TreeList` | `items`, `columns`, `getId`, `getParentId`/`getChildren`, `loadChildren`, `selectionMode`, `onMove`, `ariaLabel` | Hierarchies (chart of accounts). |
| `Pagination` | `page`, `totalPages`, `onPageChange` | |
| `DescriptionList` | `items: {term,value}[]` | Read-only key/value. |
| `Badge` | `variant: primary｜secondary｜success｜danger｜warning｜info｜light｜dark`, `size`, `outlined`, `pill`, `dot` | Status. |
| `Chip` | `label`, `active`, `count`, `icon`, `size`, `onClick` | Filter chips. |
| `Alert` | `tone: danger｜warning｜info`, `children` | Block-level state. No `success`. |
| `EmptyState` | `title`, `description`, `icon` (no `ph-` prefix), `variant`, `action`, `size`, `fill` | |
| `Skeleton` | `variant: text｜rect｜circle`, `width`, `height`, `lines` | |
| `Spinner` / `Progress` | `label`, `size` / `value`, `max`, `label` | |
| `StatCard`, `KpiMetricRow` | `items: {label,value,tone,hint,icon}[]`, `layout`, `density`, `loading` | Dashboard tiles. |
| `Timeline` / `TimelineItem` | `variant: stepper｜log`, `status`, `title`, `timestamp`, `tone` | |
| `Avatar` | `owner`, `variant`, `size`, `shape` | |
| `Text`, `Code` | `tone: default｜muted`, `as: span｜p` | Inline text, monospace (series-number `F001-00012345`). |

## Theme, i18n, gating

`ThemeProvider`, `ThemeSwitcher`, `useTheme`, `DensityProvider`, `useDensity`, `useAccentColor`,
`ACCENT_PRESETS`, `SettingsPanel`, `UiStringsProvider` + `ES_UI_STRINGS` + `useUiStrings`,
`FeatureGate`. They sit at the app root already; do not re-add them in features. Also exported:
`ThemeContext`, `DensityContext`, `EN_UI_STRINGS`, `getAvatarInitials`, `getAvatarHue`, and the
`TreeList` model helpers `buildTreeListModel`, `flattenVisibleTree`, `getDescendantIds`.

## Other packages

| Package | Reach for it when | Main exports |
| --- | --- | --- |
| `@nubitio/crud` | A list/CRUD screen or an API-backed lookup | `SchemaCrudPage`, `SmartCrudPage`, `CrudPage`, `defineResource`, `defineFields`, `textField/entityField/switchField/…` builders, `EntityDropdown`, `mapApiViolations`, `useHasPermission`, `ImportPanel`, `AuditTrailPanel` |
| `@nubitio/core` | HTTP, money, dates, events, config | `CoreHttpClient`, `getCoreApiBaseUrl`, `formatMoney`, `parseMoney`, `sumMoney`, `DateUtils`, `createCrudEvents`, `dispatch`, `useEvents`, Mercure hooks |
| `@nubitio/admin` | Shell, session, plans, notifications | `AdminShell`, `useSession`, `useFeature`, `FeatureGate`, `useNotifications`, `ToastHost`, `createNubitApp` |
| `@nubitio/dashboard` | Dashboards | `defineDashboard`, `DashboardPage`, widget builders (`statWidget`, `barChartWidget`, …) |
| `@nubitio/hydra` | API Platform schema/stores | `createHydraResourceStore`, `useResourceSchema` |
| `@nubitio/react-admin` | Umbrella re-export of the above | |

In the ERP, call the API through `httpClient` (`@shared/api/HttpClient`) and `createEndpoints`
(`@shared/api/endpoints`) rather than `CoreHttpClient` directly: they add the app's error UX.

## Missing here, build it in the ERP

`DocumentIdInput` (RUC/DNI with módulo 11 and lookup), `Money`/`MoneyInput`, `LineItemsEditor`,
`TotalsPanel`, `StatusBadge`, `SyncStatus`, `TenantSwitcher`. Compose them from the primitives
above under `assets/shared/` (cross-feature) or `assets/features/<name>/` (single feature).
