import React$1, { ReactNode, RefObject } from "react";
import { FieldOverride as FieldOverride$1 } from "@nubit/core";

//#region assets/core/field/FieldType.d.ts
declare enum FieldType {
  NONE = "none",
  TEXT = "text",
  PASSWORD = "password",
  TEXTAREA = "textarea",
  DATE = "date",
  DATETIME = "datetime",
  NUMBER = "number",
  CURRENCY = "currency",
  SELECT = "select",
  ENUM = "enum",
  ENTITY = "entity",
  RADIO = "radio",
  SWITCH = "switch",
  CHECKBOX = "checkbox",
  FILE = "file",
  TAGS = "tags",
  HTML = "html"
}
//#endregion
//#region assets/core/field/FilterRule.d.ts
interface FilterRule {
  field: string;
  operator: string;
  value: string | number | boolean;
}
//#endregion
//#region assets/core/field/ValidationRule.d.ts
type RequiredRule = {
  type: 'required';
  options: {
    message?: string;
  };
};
type EmailRule = {
  type: 'email';
  options: {
    message?: string;
  };
};
type NumericRule = {
  type: 'numeric';
  options: {
    message?: string;
  };
};
type PatternRule = {
  type: 'pattern';
  options: {
    pattern: string;
    message?: string;
  };
};
type StringLengthRule = {
  type: 'stringLength';
  options: {
    min?: number;
    max?: number;
    message?: string;
  };
};
type RangeRule = {
  type: 'range';
  options: {
    min?: number;
    max?: number;
    message?: string;
  };
};
type CompareRule = {
  type: 'compare';
  options: {
    comparisonTarget: () => any;
    comparisonType?: '==' | '!=' | '>' | '<' | '>=' | '<=';
    message?: string;
  };
};
type CustomRule = {
  type: 'custom';
  options: {
    validationCallback: (options: any) => boolean;
    message?: string;
  };
};
type AsyncRule = {
  type: 'async';
  options: {
    validationCallback: (options: any) => Promise<boolean>;
    message?: string;
  };
};
type ValidationRule = RequiredRule | EmailRule | NumericRule | PatternRule | StringLengthRule | RangeRule | CompareRule | CustomRule | AsyncRule;
//#endregion
//#region assets/core/data/DataRecord.d.ts
type DataRecord = Record<string, unknown>;
//#endregion
//#region assets/core/field/FieldCallbacks.d.ts
interface GridCellContext<TData extends DataRecord = Record<string, any>, TValue = any> {
  value?: TValue;
  data: TData;
  rowIndex?: number;
  columnIndex?: number;
  [key: string]: unknown;
}
/**
 * Callback for grid column cell rendering.
 * Receives the app-owned cell context; UI adapters may provide extra fields.
 *
 * @example
 *   .formatter(cell => cell.value?.name)
 *   .formatter(cell => NumberUtils.formatCurrency(cell.value))
 */
type FormatterFn = (cell: GridCellContext) => React$1.ReactNode;
/**
 * Callback for grid column `setCellValue` — called when the user edits a cell in an
 * inline grid editor. The active grid adapter passes three arguments:
 *   - `newData`       – partial row data object to mutate with the new value
 *   - `value`         – the new cell value
 *   - `currentRowData`– the full row data before the edit
 *
 * @example
 *   .onChange(function(row, value, currentRow) { row.total = value * currentRow.qty; })
 */
type GridOnChangeFn = (newData: DataRecord, value: unknown, currentRowData: DataRecord) => void | Promise<void>;
/**
 * Callback for form field `onValueChanged` — called when the user changes a form
 * field value. The form infrastructure unwraps adapter events and passes the raw value directly.
 *
 * @example
 *   .onChange(value => { selectedWarehouseId = value as number; })
 */
type FormOnChangeFn = (value: unknown) => void | Promise<void>;
/** Union accepted by `.onChange()` — covers both grid and form contexts. */
type OnChangeFn = GridOnChangeFn | FormOnChangeFn;
/**
 * Callback for `itemRender` in dropdown/select editors — renders a single list item.
 *
 * @example
 *   .itemFormatter(item => `${item.code} – ${item.name}`)
 */
type ItemFormatterFn = (item: unknown) => React$1.ReactNode;
//#endregion
//#region assets/core/field/Field.d.ts
type LoadOption = Record<string, any>;
interface Field {
  isIdentity: boolean;
  type: FieldType;
  col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | undefined;
  name: string;
  label: string;
  width: string | number | undefined;
  height: number | null;
  minWidth: number | undefined;
  minHeight: number | undefined;
  align: 'left' | 'center' | 'right' | undefined;
  sortable: boolean;
  filterable: boolean;
  hideable: boolean;
  validators: ValidationRule[];
  mode: 'remote' | 'local' | 'none';
  url: string | undefined;
  loadOptions: LoadOption[];
  filters: FilterRule[];
  byKeyUrl: string | null;
  textField: string;
  valueField: string;
  valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime';
  format: string;
  selectedFilterOperation: '<' | '<=' | '<>' | '=' | '>' | '>=' | 'between' | 'contains' | 'endswith' | 'notcontains' | 'startswith' | undefined;
  filterValue: string | number | boolean | Date | null | undefined;
  data: DataRecord[];
  formatter: FormatterFn | undefined;
  itemFormatter: ItemFormatterFn | undefined;
  visible: boolean;
  defaultValue: string | number | boolean | Date | null | undefined | (() => unknown);
  autoSelectIfSingle?: boolean;
  onChange: OnChangeFn | undefined;
  onSelect: ((e: unknown) => void) | undefined;
  onClick: ((e: unknown) => void) | undefined;
  onKeyPress: ((e: KeyboardEvent) => void) | undefined;
  readonly: boolean;
  disabled: boolean;
  hidden: boolean;
  required: boolean;
  precision: number;
  accept: string | null | undefined;
  buttons: DataRecord[];
  hidingPriority: number | undefined;
  searchEnabled: boolean;
  searchExpr: string[] | null;
  fixed: boolean;
  fixedPosition: 'left' | 'right' | undefined;
  helpText: string | undefined;
  contentRender: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined;
  visibleOnForm: boolean;
  maxLength: number | undefined;
  multiple: boolean;
  editorOptions: DataRecord | undefined;
  sendAsString: boolean;
  /** Column display order hint set via `x-crud.order` from Hydra metadata. */
  order?: number;
  /** Returns true when the field should be visible */
  visibleWhen?: (formData: DataRecord) => boolean;
  /** Returns true when the field should be disabled (read-only in form) */
  disabledWhen?: (formData: DataRecord) => boolean;
  /** Derived value — field is rendered as read-only with this computed value */
  computed?: (formData: DataRecord) => unknown;
  /** When listed fields change, reload this field's options (entityField / enumField) */
  dependsOn?: string[];
  /** UI-only RBAC — field is hidden/readonly based on user roles */
  permissions?: {
    /** Roles that can SEE this field. Omit = visible to all. */visible?: string[]; /** Roles that can EDIT this field. Omit = editable by all who can see it. */
    editable?: string[];
  };
}
/**
 * Generic extension of `Field` that narrows `defaultValue` and `onChange`
 * to the value types of a specific resource record type `T`.
 *
 * Use `FieldDef<T>` when you want type-safe field definitions for a known
 * resource shape. Falls back to `DataRecord` when `T` is omitted.
 *
 * `defaultValue` and `onChange` are re-declared with generic-aware types;
 * all other `Field` properties are inherited unchanged.
 */
type FieldDef<T extends DataRecord = DataRecord> = Omit<Field, 'defaultValue' | 'onChange'> & {
  defaultValue?: T[keyof T] | (() => T[keyof T]);
  onChange?: ((value: T[keyof T]) => void | Promise<void>) | GridOnChangeFn;
};
//#endregion
//#region assets/core/event/EventTypes.d.ts
/**
 * Typed event name interfaces for core components.
 * Each field is optional — only declare the events your feature uses.
 */
interface DataGridEventNames {
  ADD?: string;
  EDIT?: string;
  DELETE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
  EXPORT?: string;
}
interface FormEventNames {
  ADD?: string;
  EDIT?: string;
  DELETE?: string;
  SAVE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
}
interface DialogEventNames {
  ADD?: string;
  EDIT?: string;
  SAVE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
}
interface ToolbarButtonItem {
  icon?: string;
  text: string;
  hint?: string;
  /** Semantic button type. Common values: 'default' | 'normal' | 'danger' | 'success' | 'back' */
  type?: string;
  onClick?: () => void;
  disabled?: boolean;
  visible?: boolean;
}
//#endregion
//#region assets/core/form/FormDataSnapshot.d.ts
type FormDataRecord = DataRecord;
type FormDataSnapshot = FormDataRecord;
type FormDataChangedHandler = (formData: FormDataSnapshot) => void;
//#endregion
//#region assets/core/form/FormHandle.d.ts
interface FormHandle {
  getFormData: () => FormDataRecord | undefined;
  getFieldValue: (name: string) => unknown;
  setFieldValue: (name: string, value: unknown) => void;
  getDetailData: () => FormDataRecord[] | undefined;
  setDetailData: (data: FormDataRecord[]) => void;
  saveDetailData: () => Promise<void> | void;
  reloadDetailData: () => void;
  setValues: (data: FormDataRecord) => void;
  setReadonly: (field: string, value: boolean) => void;
  setDisabled: (field: string, value: boolean) => void;
  enableValidation: (field: string) => void;
  disableValidation: (field: string) => void;
  setError: (field: string, message: string) => void;
  showField: (field: string) => void;
  hideField: (field: string) => void;
  validate: () => boolean;
  setIsEdit: (value: boolean) => void;
}
//#endregion
//#region assets/core/form/FormLayout.d.ts
interface FormTab {
  label: string;
  fields: string[];
  icon?: string;
}
interface FormSection {
  label: string;
  fields: string[];
  collapsible?: boolean;
  defaultCollapsed?: boolean;
}
type FormLayout = {
  type: 'tabs';
  tabs: FormTab[];
} | {
  type: 'sections';
  sections: FormSection[];
};
//#endregion
//#region assets/core/crud/BulkAction.d.ts
interface BulkAction {
  key: string;
  label: string;
  icon?: string;
  confirmMessage?: string;
  onAction: (selectedIds: (string | number)[]) => void | Promise<void>;
}
//#endregion
//#region assets/core/crud/ColumnPreset.d.ts
interface ColumnPreset {
  key: string;
  label: string;
  columns: string[];
}
//#endregion
//#region assets/core/crud/AuditTrail.d.ts
interface AuditEntry {
  id: string | number;
  timestamp: string;
  user: string;
  action: 'create' | 'update' | 'delete';
  changes: Record<string, {
    before: unknown;
    after: unknown;
  }>;
}
interface AuditTrailConfig {
  enabled: boolean;
  apiUrl: string | ((id: string | number) => string);
  renderEntry?: (entry: AuditEntry) => ReactNode;
}
//#endregion
//#region assets/core/crud/fieldOperationSemantics.d.ts
type SmartCrudOperation = 'create' | 'edit';
type SmartCrudFieldOperation = SmartCrudOperation;
type SmartCrudFieldOperationFlag = boolean | Partial<Record<SmartCrudFieldOperation, boolean>>;
interface SmartCrudFieldOperationState {
  visible?: boolean;
  required?: boolean;
  readonly?: boolean;
  disabled?: boolean;
}
interface SmartCrudFieldOperationBehavior {
  only?: SmartCrudFieldOperation | readonly SmartCrudFieldOperation[];
  visible?: SmartCrudFieldOperationFlag;
  required?: SmartCrudFieldOperationFlag;
  readonly?: SmartCrudFieldOperationFlag;
  disabled?: SmartCrudFieldOperationFlag;
  create?: SmartCrudFieldOperationState;
  edit?: SmartCrudFieldOperationState;
}
//#endregion
//#region assets/core/crud/fieldContract.d.ts
type SmartCrudRecord = object;
type SmartCrudModelFieldKey<T extends SmartCrudRecord> = Extract<keyof T, string>;
type SmartCrudFieldRecord<T extends SmartCrudRecord> = T & DataRecord;
type SmartCrudFieldSourceMode = 'hydra' | 'manual';
type SmartCrudFieldResolutionStrategy = 'augment' | 'replace';
type SmartCrudFieldPatch<T extends SmartCrudRecord> = Partial<Omit<FieldDef<SmartCrudFieldRecord<T>>, 'name' | 'defaultValue' | 'onChange' | 'required' | 'readonly' | 'disabled'>> & {
  defaultValue?: FieldDef<SmartCrudFieldRecord<T>>['defaultValue'];
  onChange?: FieldDef<SmartCrudFieldRecord<T>>['onChange'];
  required?: boolean;
  readonly?: boolean;
  disabled?: boolean;
  operation?: SmartCrudFieldOperationBehavior;
};
type SmartCrudManualField<T extends SmartCrudRecord, Name extends string = string> = Omit<FieldDef<SmartCrudFieldRecord<T>>, 'name' | 'defaultValue' | 'onChange' | 'required' | 'readonly' | 'disabled'> & {
  name: Name;
  defaultValue?: FieldDef<SmartCrudFieldRecord<T>>['defaultValue'];
  onChange?: FieldDef<SmartCrudFieldRecord<T>>['onChange'];
  required?: boolean;
  readonly?: boolean;
  disabled?: boolean;
  operation?: SmartCrudFieldOperationBehavior;
};
interface SmartCrudOverrideField<T extends SmartCrudRecord, Key extends SmartCrudModelFieldKey<T> = SmartCrudModelFieldKey<T>> {
  kind: 'override';
  key: Key;
  patch: SmartCrudFieldPatch<T>;
}
interface SmartCrudRemoveField<T extends SmartCrudRecord, Key extends SmartCrudModelFieldKey<T> = SmartCrudModelFieldKey<T>> {
  kind: 'remove';
  key: Key;
}
interface SmartCrudPrependField<T extends SmartCrudRecord, Name extends string = string> {
  kind: 'prepend';
  field: SmartCrudManualField<T, Name>;
}
interface SmartCrudAppendField<T extends SmartCrudRecord, Name extends string = string> {
  kind: 'append';
  field: SmartCrudManualField<T, Name>;
}
type SmartCrudHydraFieldDirective<T extends SmartCrudRecord> = SmartCrudOverrideField<T> | SmartCrudRemoveField<T> | SmartCrudPrependField<T> | SmartCrudAppendField<T>;
interface SmartCrudHydraFieldContract<T extends SmartCrudRecord> {
  source: 'hydra';
  strategy: 'augment';
  directives: readonly SmartCrudHydraFieldDirective<T>[];
}
interface SmartCrudManualFieldContract<T extends SmartCrudRecord> {
  source: 'manual';
  strategy: 'replace';
  fields: readonly SmartCrudManualField<T>[];
}
type SmartCrudFieldContract<T extends SmartCrudRecord> = SmartCrudHydraFieldContract<T> | SmartCrudManualFieldContract<T>;
declare function defineFields<T extends SmartCrudRecord>(contract: SmartCrudHydraFieldContract<T>): SmartCrudHydraFieldContract<T>;
declare function defineFields<T extends SmartCrudRecord>(contract: SmartCrudManualFieldContract<T>): SmartCrudManualFieldContract<T>;
declare const defineFieldContract: typeof defineFields;
//#endregion
//#region assets/core/crud/ResourceConfig.d.ts
/**
 * Generic factory that receives a form ref and returns typed field definitions.
 * Defaults to `DataRecord` for backward compatibility.
 */
type FieldsFactory<T extends DataRecord = DataRecord> = (formRef: RefObject<FormHandle | null>) => FieldDef<T>[];
/**
 * URL-based deep-linking and filter sync configuration for SmartCrudPage.
 * All fields are optional — if `routing` is absent, no URL behaviour is applied.
 */
interface ResourceRouting {
  /**
   * URL param name for deep-linking a specific record.
   * e.g. `routeParam: 'id'` →  `/products/42`  opens record 42 in the edit dialog
   *                          →  `/products/new`  opens the add dialog
   */
  routeParam?: string;
  /**
   * When true, grid filter state is synced to URL query params.
   * e.g. `?name=foo&status=active`
   */
  syncFiltersToUrl?: boolean;
}
/** Raw permission spec: each entry may be a static boolean or a zero-arg callable. */
interface ResourcePermissions {
  canAdd?: boolean | (() => boolean);
  canEdit?: boolean | (() => boolean);
  canDelete?: boolean | (() => boolean);
  canExport?: boolean | (() => boolean);
  canBulkDelete?: boolean | (() => boolean);
}
interface ResourceConfig<T extends DataRecord = DataRecord> {
  id: string;
  title: string;
  apiUrl: string;
  /**
   * Legacy/manual field entrypoint.
   * Prefer `fieldContract` for canonical SmartCrud authoring; this remains as
   * a compatibility path for manual screens and in-flight migrations.
   */
  fields: Field[] | FieldDef<T>[] | FieldsFactory<T>;
  /**
   * Canonical production field contract for SmartCrud.
   * SmartCrud runtime treats this as authoritative over legacy
   * `fields` + `fieldOverrides` combinations whenever it is present.
   */
  fieldContract?: SmartCrudFieldContract<T>;
  /** Optional: inject a pre-existing event bus (e.g. a module-level singleton).
   *  When omitted, `useCrudPage` creates fresh events via `createCrudEvents(id)`. */
  events?: FormEventNames;
  detailFields?: Field[];
  detailUrl?: string;
  detailPropertyName?: string;
  sort?: Array<{
    selector: string;
    desc: boolean;
  }>;
  filter?: FilterRule[];
  mode?: 'normal' | 'minimal';
  paging?: boolean;
  /** Enables adapter-provided header filter dropdowns. Disabled by default; filter row remains the default. */
  headerFilter?: boolean;
  stateStoring?: boolean;
  /** @deprecated Use `permissions.canAdd` instead. */
  allowAdd?: boolean;
  /** @deprecated Use `permissions.canEdit` instead. */
  allowEdit?: boolean;
  /** @deprecated Use `permissions.canDelete` instead. */
  allowDelete?: boolean;
  /** @deprecated Use `permissions.canExport` instead. */
  allowExport?: boolean;
  /** Fine-grained RBAC permission overrides. Each entry takes precedence over the
   *  corresponding `allowX` boolean alias. May be static or a callable that is
   *  evaluated at render time. */
  permissions?: ResourcePermissions;
  dialogWidth?: number;
  dialogHeight?: number;
  format?: 'json' | 'multipart';
  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];
  onSaveSuccess?: (response: T) => void;
  onSaveError?: (error?: unknown) => void;
  onDeleteSuccess?: (response: T) => void;
  onDeleteError?: (error?: unknown) => void;
  /** Bulk actions rendered in the bulk toolbar when rows are selected. */
  bulkActions?: BulkAction[];
  /**
   * Grid editing mode. Inline modes are interpreted by the active grid adapter.
   * - `'popup'` (default) opens the resource dialog and form.
   * - `'row'` | `'cell'` | `'batch'` enable inline editing when supported.
   */
  editMode?: 'popup' | 'row' | 'cell' | 'batch';
  /** Optional layout descriptor for the dialog form: tabs or collapsible sections. */
  formLayout?: FormLayout;
  /** Named column visibility presets. When defined, a preset selector is shown in the toolbar. */
  columnPresets?: ColumnPreset[];
  /** Key of the preset to activate by default (overridden by any localStorage value). */
  defaultPreset?: string;
  /** Optional audit trail configuration. When enabled, shows a "Historial" button and panel. */
  auditTrail?: AuditTrailConfig;
  /** Optional URL routing configuration — enables deep-linking and filter sync. */
  routing?: ResourceRouting;
  /**
   * Called whenever a detail-grid row is added, updated, or removed.
   * Use this to recompute header-form fields that depend on detail data
   * (e.g., recalculating a `total` field from line items).
   *
   * @param formRef - ref to the form handle (call `formRef.current?.setFieldValue(...)` etc.)
   */
  onDetailRowsChanged?: (formRef: RefObject<FormHandle | null>) => void;
  /**
   * Enable or disable real-time Mercure subscription for this resource.
   *
   * - `true` (default): `SmartCrudPage` opens an SSE subscription to the Mercure hub
   *   and refreshes the grid automatically when another session modifies the resource.
   * - `false`: disables the Mercure subscription for this resource. The grid still
   *   refreshes via the existing RxJS event bus for operations in the same session.
   *
   * Has no effect if the Mercure hub URL has not been discovered (graceful degradation).
   */
  mercure?: boolean;
  /**
   * Internal: HTTP methods inferred from `hydra:supportedOperation` in the API doc.
   * Populated automatically by SmartCrudPage from useResourceSchema.
   * Do NOT set this manually — it is overridden by any explicit `allowX` / `permissions` config.
   * e.g. ['GET', 'POST', 'PATCH', 'DELETE']
   */
  _supportedOperations?: string[];
}
//#endregion
//#region assets/core/crud/defineResource.d.ts
/**
 * Overload 1 — Zero config: derive id from URL.
 * `mercure` defaults to `true` (opt-in) when not specified.
 */
declare function defineResource(apiUrl: string): ResourceConfig;
/**
 * Overload 2 — URL + partial overrides.
 * Pass `mercure: false` to disable real-time Mercure subscription for this resource.
 * When omitted, `mercure` is effectively `true` (opt-in by default).
 */
declare function defineResource<T extends DataRecord>(apiUrl: string, overrides: Omit<ResourceConfig<T>, 'apiUrl'> & {
  id?: string;
}): ResourceConfig<T>;
/** Overload 3 — Full config object (legacy / full control — retrocompatible). */
declare function defineResource<T extends DataRecord = DataRecord>(config: ResourceConfig<T>): ResourceConfig<T>;
//#endregion
//#region assets/core/crud/usePermissions.d.ts
/** Fully-resolved, all-required permissions (guaranteed booleans). */
interface ResolvedPermissions {
  canAdd: boolean;
  canEdit: boolean;
  canDelete: boolean;
  canExport: boolean;
  canBulkDelete: boolean;
}
/**
 * Resolves RBAC permissions for a resource with the following precedence
 * (highest → lowest):
 *   1. `resource.permissions.canX` — callable or boolean override
 *   2. `resource.allowX`           — legacy boolean alias
 *   3. inferred from `supportedOperations` (hydra:supportedOperation methods)
 *   4. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
 *
 * The `supportedOperations` parameter comes from `useResourceSchema` and is
 * only consulted when neither a `resource.permissions.canX` nor a
 * `resource.allowX` value was explicitly provided by the caller.
 *
 * Memoized on `resource.id` — stable reference across re-renders.
 */
declare function usePermissions(resource: ResourceConfig, supportedOperations?: string[]): ResolvedPermissions;
//#endregion
//#region assets/core/crud/useSelectionState.d.ts
interface SelectionState {
  selectedIds: (string | number)[];
  selectedCount: number;
  hasSelection: boolean;
  clearSelection: () => void;
}
declare function useSelectionState(identityField: string): SelectionState & {
  onSelectionChanged: (selectedRows: DataRecord[]) => void;
};
//#endregion
//#region assets/core/crud/useColumnPreset.d.ts
interface ColumnPresetState {
  activePreset: string | null;
  setPreset: (key: string | null) => void;
  visibleColumns: string[] | null;
}
//#endregion
//#region assets/core/crud/resolveCrudResource.d.ts
type ResolvedCrudResource<T extends DataRecord> = ResourceConfig<T> & {
  events: FormEventNames;
};
//#endregion
//#region assets/core/crud/useCrudPage.d.ts
declare function useCrudPage<T extends DataRecord = DataRecord>(resource: ResourceConfig<T>): {
  events: FormEventNames;
  resource: ResolvedCrudResource<T>;
  fields: Field[];
  formRef: React.RefObject<FormHandle | null>;
  permissions: ResolvedPermissions;
  selectionState: SelectionState & {
    onSelectionChanged: (rows: DataRecord[]) => void;
  };
  presetState: ColumnPresetState;
};
//#endregion
//#region assets/core/crud/CrudPage.d.ts
interface CrudPageProps<T extends DataRecord = DataRecord> {
  resource: ResourceConfig<T>;
  /**
   * Called whenever any form field value changes.
   * Receives a snapshot of the full current form data object.
   * Wire this to SmartCrudPage's formData state for reactive rule evaluation.
   */
  onFormDataChange?: (data: FormDataRecord) => void;
  /**
   * When provided, open the edit dialog for this record on mount.
   * Enables URL deep-linking (e.g. /products/42 → edit record 42).
   * Defaults to null — existing behaviour unchanged when omitted.
   */
  initialRecordId?: string | number | null;
  /**
   * When true, open the add dialog on mount.
   * Enables URL deep-linking (e.g. /products/new → open add dialog).
   * Defaults to false — existing behaviour unchanged when omitted.
   */
  initialIsNew?: boolean;
  computedValues?: FormDataRecord;
  initialFilters?: Record<string, string>;
  onFiltersChange?: (filters: Record<string, string>) => void;
}
/**
 * Public export — wraps CrudPageInner in its own DialogStoreProvider so that
 * existing pages do not need to add a provider themselves.
 */
declare const CrudPage: <T extends DataRecord = DataRecord>({
  resource,
  onFormDataChange,
  initialRecordId,
  initialIsNew,
  computedValues,
  initialFilters,
  onFiltersChange
}: CrudPageProps<T>) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/crud/resolveSmartCrudFields.d.ts
type FieldOverride = Partial<Field> & {
  key: string;
};
interface ResolveSmartCrudFieldsOptions<T extends SmartCrudRecord> {
  baselineFields?: readonly Field[];
  contract?: SmartCrudFieldContract<T>;
  legacyOverrides?: readonly FieldOverride[];
}
declare function resolveSmartCrudFields<T extends SmartCrudRecord>(options: ResolveSmartCrudFieldsOptions<T>): Field[];
//#endregion
//#region assets/core/crud/SmartCrudPage.d.ts
interface SmartCrudPageProps<T extends DataRecord = DataRecord> {
  resource: ResourceConfig<T>;
  /**
   * Legacy per-field overrides applied on top of auto-inferred Hydra fields.
   * Each entry must contain a `key` matching the field's `name`, plus any
   * `Field` properties to override (e.g. `{ key: 'name', label: 'Custom' }`).
   * Ignored when the canonical `resource.fieldContract` is present.
   * Has no effect when `resource.fields` is a non-empty manual array.
   */
  fieldOverrides?: FieldOverride$1[];
}
/**
 * SmartCrudPage — wraps CrudPage with Hydra auto-discovery and declarative rules.
 *
 * ## Field resolution
 * - If `resource.fields` is a non-empty array, it is used as-is (pass-through).
 * - If `resource.fields` is empty / absent, fields are inferred from the Hydra
 *   API spec at `/api/docs.json` via `useResourceSchema`.
 * - While the schema loads: renders a skeleton loader.
 * - If the schema fetch errors: renders a user-visible error with a retry button.
 *
 * ## Declarative rules pipeline (via `useSmartCrudFields`)
 *   1. `useFieldPermissions` — RBAC: hides or disables fields based on user roles
 *   2. `useConditionalRules` — evaluates visibleWhen / disabledWhen / computed
 *   3. `useDependsOn`        — invalidates entity queries when dependency fields change
 *
 * ## Operation & form state (via `useSmartCrudOperation`)
 *   Manages `activeOperation` + `formData` as a unified state pair, synced
 *   from routing changes and the resource event bus (ADD/EDIT/CANCEL/SUCCESS).
 *
 * URL deep-linking is wired via `initialRecordId` / `initialIsNew` props on CrudPage.
 */
declare function SmartCrudPage<T extends DataRecord = DataRecord>({
  resource,
  fieldOverrides
}: SmartCrudPageProps<T>): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/crud/SmartCrudRolesContext.d.ts
declare function SmartCrudRolesProvider({
  children,
  roles
}: React$1.PropsWithChildren<{
  roles?: string[];
}>): import("react/jsx-runtime").JSX.Element;
declare function useSmartCrudRoles(): string[];
//#endregion
//#region assets/core/event/createScopedEventBus.d.ts
/**
 * A `FormEventNames` object whose event strings are namespaced with a
 * per-instance scope ID to prevent cross-component event leakage.
 * The extra `_scopeId` property carries the raw 8-char scope token for
 * diagnostics / testing.
 */
type ScopedFormEventNames = FormEventNames & {
  readonly _scopeId: string;
};
/**
 * Creates a scoped event bus for the given resource.
 *
 * Internally this generates an 8-character random scope token and delegates to
 * `createCrudEvents(`${resourceId}:${scopeId}`)`. This means each call produces
 * a unique set of event name strings, even for the same `resourceId`, which
 * prevents accidental cross-instance event coupling when multiple CRUD pages
 * for the same resource are mounted simultaneously.
 *
 * @param resourceId - The resource id (e.g. `'product'`).
 * @returns A `ScopedFormEventNames` object with a stable `_scopeId` tag.
 */
declare function createScopedEventBus(resourceId: string): ScopedFormEventNames;
//#endregion
//#region assets/core/i18n/CoreTranslationKeys.d.ts
interface CoreTranslationKeys {
  'crudPage.selectedCount': string;
  'crudPage.auditTrailButton': string;
  'crudPage.allColumns': string;
  'crudPage.columnsLabel': string;
  'crudPage.dialogTitleAdd': string;
  'crudPage.dialogTitleEdit': string;
  'crudPage.dialogTitleView': string;
  'auditTrail.title': string;
  'auditTrail.closeButton': string;
  'auditTrail.selectRecord': string;
  'auditTrail.loading': string;
  'auditTrail.error': string;
  'auditTrail.empty': string;
  'auditTrail.action.create': string;
  'auditTrail.action.update': string;
  'auditTrail.action.delete': string;
  'form.detailRequired': string;
  'form.detailEmpty': string;
  'form.validationError': string;
  'form.loading': string;
  'form.groupOther': string;
  'grid.noRecords': string;
  'grid.selectRequired': string;
  'grid.loading': string;
  'grid.buttonNew': string;
  'grid.buttonEdit': string;
  'grid.buttonDelete': string;
  'grid.buttonRefresh': string;
  'grid.rowsPerPage': string;
  'dialog.buttonCancel': string;
  'dialog.buttonSave': string;
  'dialog.confirmDelete': string;
  'dialog.confirmDeleteTitle': string;
}
//#endregion
//#region assets/core/i18n/useCoreTranslation.d.ts
declare function useCoreTranslation(): {
  t: (key: keyof CoreTranslationKeys, options?: DataRecord) => string;
};
//#endregion
//#region assets/core/i18n/initCoreI18n.d.ts
declare function initCoreI18n(): void;
//#endregion
//#region assets/core/field/BaseFieldBuilder.d.ts
declare class BaseFieldBuilder {
  protected _field: Field;
  constructor(type: FieldType);
  isIdentity(isIdentity: boolean): this;
  col(col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12): this;
  name(name: string): this;
  label(label: string): this;
  width(width: number | string): this;
  height(height: number): this;
  minWidth(minWidth: number): this;
  minHeight(minHeight: number): this;
  align(align: 'left' | 'center' | 'right'): this;
  sortable(sortable: boolean): this;
  filterable(filterable: boolean): this;
  hideable(hideable: boolean): this;
  validators(validators: ValidationRule[]): this;
  mode(mode: 'remote' | 'local' | 'none'): this;
  valueType(valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime'): this;
  filterValue(filterValue: string | number | boolean | Date | null | undefined): this;
  data(data: DataRecord[]): this;
  format(format: string): this;
  formatter(formatter: FormatterFn): this;
  itemFormatter(itemFormatter: ItemFormatterFn): this;
  visible(visible: boolean): this;
  defaultValue(defaultValue: string | number | boolean | Date | null | undefined | (() => unknown)): this;
  onChange(onChange: OnChangeFn): this;
  onSelect(onSelect: (e: unknown) => void): this;
  onClick(onClick: (e: unknown) => void): this;
  readonly(readonly: boolean): this;
  disabled(disabled: boolean): this;
  hidden(hidden: boolean): this;
  required(required: boolean): this;
  precision(value: number): this;
  accept(value: string | null | undefined): this;
  buttons(value: DataRecord[]): this;
  hidingPriority(value: number): this;
  searchEnabled(value: boolean): this;
  searchExpr(value: string[]): this;
  fixed(value: boolean): this;
  fixedPosition(value: 'left' | 'right'): this;
  helpText(value: string): this;
  contentRender(value: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined): this;
  visibleOnForm(value: boolean): this;
  onKeyPress(param: (e: KeyboardEvent) => void): this;
  autoSelectIfSingle(value: boolean): this;
  url(url: string): this;
  loadOptions(param: LoadOption[]): this;
  filters(filters: FilterRule[]): this;
  byKeyUrl(byKeyUrl: string): this;
  textField(textField: string): this;
  valueField(valueField: string): this;
  selectedFilterOperation(operation: '<' | '<=' | '<>' | '=' | '>' | '>=' | 'between' | 'contains' | 'endswith' | 'notcontains' | 'startswith'): this;
  sendAsString(value: boolean): this;
  multiple(value: boolean): this;
  editorOptions(param: DataRecord): this;
  maxLength(value: number): this;
  visibleWhen(fn: (formData: DataRecord) => boolean): this;
  disabledWhen(fn: (formData: DataRecord) => boolean): this;
  computed(fn: (formData: DataRecord) => unknown): this;
  dependsOn(fields: string[]): this;
  permissions(perms: {
    visible?: string[];
    editable?: string[];
  }): this;
  build(): Field;
}
//#endregion
//#region assets/core/field/FieldBuilders.d.ts
/**
 * Factory for the standard hidden identity field (type NONE, name 'id').
 *
 * Usage:
 *   identityField().build()
 *   identityField().required(true).build()
 *   identityField().valueType('number').build()
 *
 * Defaults set automatically:
 *   isIdentity(true), visible(false), required(true), name('id'), label('Id')
 */
declare class IdentityFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function identityField(): IdentityFieldBuilder;
/**
 * Builder for ENTITY (remote select) fields.
 *
 * Required constructor args: url, valueField, textField — the three properties
 * that are ALWAYS present on every ENTITY field in the codebase.
 *
 * Usage:
 *   new EntityFieldBuilder('customers', 'id', 'businessName')
 *     .label('Cliente')
 *     .name('customer')
 *     .mode('remote')
 *     .formatter(cell => cell.value?.businessName)
 *     .build()
 */
declare class EntityFieldBuilder extends BaseFieldBuilder {
  constructor(url: string, valueField: string, textField: string);
}
declare function entityField(url: string, valueField: string, textField: string): EntityFieldBuilder;
/**
 * Builder for CURRENCY fields.
 *
 * Defaults applied automatically:
 *   sendAsString(true), align('right')
 *
 * Usage:
 *   new CurrencyFieldBuilder()
 *     .name('grandTotal')
 *     .label('Total')
 *     .formatter(cell => NumberUtils.formatCurrency(cell.value, cell.data?.currency))
 *     .build()
 */
declare class CurrencyFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function currencyField(): CurrencyFieldBuilder;
/**
 * Factory for image upload fields (FILE type).
 *
 * Defaults: url=Constants.API_BASE_URL+'media', accept='image/*', align='center'
 * Formatter must be provided by the caller since it depends on the data shape.
 *
 * Usage:
 *   imageField(Constants.API_BASE_URL)
 *     .name('photo')
 *     .label('Imagen')
 *     .formatter(e => SharedUtils.imageFormatter(e.value?.path, e.data?.name))
 *     .build()
 */
declare class ImageFieldBuilder extends BaseFieldBuilder {
  constructor(apiBaseUrl: string);
}
declare function imageField(apiBaseUrl: string): ImageFieldBuilder;
/**
 * Builder for DATE fields.
 *
 * Defaults applied automatically:
 *   type = DATE, defaultValue = new Date()
 *
 * Usage:
 *   dateField()
 *     .name('issueDate')
 *     .label('Fecha')
 *     .formatter(cell => SharedUtils.dateFormatter(cell.value))
 *     .build()
 */
declare class DateFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function dateField(): DateFieldBuilder;
/**
 * Builder for DATETIME fields.
 *
 * Defaults applied automatically:
 *   type = DATETIME, defaultValue = new Date()
 *
 * Usage:
 *   datetimeField()
 *     .name('openedAt')
 *     .label('Apertura')
 *     .formatter(cell => SharedUtils.dateTimeFormatter(cell.value))
 *     .build()
 */
declare class DateTimeFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function datetimeField(): DateTimeFieldBuilder;
/**
 * Builder for NUMBER fields.
 *
 * Usage:
 *   numberField()
 *     .name('quantity')
 *     .label('Cantidad')
 *     .precision(2)
 *     .align('center')
 *     .build()
 */
declare class NumberFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function numberField(): NumberFieldBuilder;
/** A single option item for ENUM / SELECT fields. */
type EnumOption = {
  value: string | number;
  text: string;
};
/**
 * Builder for ENUM (local select) fields.
 *
 * The options array is a required constructor arg — every ENUM field in the
 * codebase always provides it.
 *
 * Usage:
 *   enumField([
 *     { value: 'income', text: 'Ingreso' },
 *     { value: 'expense', text: 'Egreso' },
 *   ])
 *     .name('type')
 *     .label('Tipo')
 *     .formatter(cell => cell.value === 'income' ? 'Ingreso' : 'Egreso')
 *     .build()
 */
declare class EnumFieldBuilder extends BaseFieldBuilder {
  constructor(options: EnumOption[]);
}
declare function enumField(options: EnumOption[]): EnumFieldBuilder;
/**
 * Builder for TEXT (plain string input) fields.
 *
 * Usage:
 *   textField()
 *     .name('businessName')
 *     .label('Razón social')
 *     .required(true)
 *     .build()
 */
declare class TextFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function textField(): TextFieldBuilder;
/**
 * Builder for TEXTAREA (multi-line text) fields.
 *
 * Usage:
 *   textareaField()
 *     .name('note')
 *     .label('Nota')
 *     .build()
 */
declare class TextareaFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function textareaField(): TextareaFieldBuilder;
/**
 * Builder for NONE (display-only, no editor) fields.
 *
 * Usage:
 *   noneField()
 *     .name('sunatStatus')
 *     .label('Envío sunat')
 *     .formatter(cell => SharedUtils.billStatusFormatter(cell.value))
 *     .build()
 */
declare class NoneFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function noneField(): NoneFieldBuilder;
/**
 * Builder for SWITCH (boolean toggle) fields.
 *
 * Usage:
 *   switchField()
 *     .name('active')
 *     .label('Activo')
 *     .defaultValue(true)
 *     .formatter(SharedUtils.booleanFormatter)
 *     .build()
 */
declare class SwitchFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function switchField(): SwitchFieldBuilder;
/**
 * Builder for SELECT (local dropdown, value+text pairs) fields.
 *
 * Usage:
 *   selectField()
 *     .name('currency')
 *     .label('Moneda')
 *     .data([{ value: 'PEN', text: 'Soles' }, { value: 'USD', text: 'Dólares' }])
 *     .build()
 */
declare class SelectFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function selectField(): SelectFieldBuilder;
/**
 * Builder for CHECKBOX (boolean check) fields.
 *
 * Usage:
 *   checkboxField()
 *     .name('emitInvoice')
 *     .label('Emitir comprobante')
 *     .defaultValue(false)
 *     .build()
 */
declare class CheckboxFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function checkboxField(): CheckboxFieldBuilder;
/**
 * Builder for PASSWORD (masked text input) fields.
 *
 * Usage:
 *   passwordField()
 *     .name('password')
 *     .label('Contraseña')
 *     .required(true)
 *     .build()
 */
declare class PasswordFieldBuilder extends BaseFieldBuilder {
  constructor();
}
declare function passwordField(): PasswordFieldBuilder;
//#endregion
//#region assets/core/field/FieldBuilder.d.ts
declare class FieldBuilder {
  private readonly _field;
  constructor();
  isIdentity(isIdentity: boolean): FieldBuilder;
  type(type: FieldType): FieldBuilder;
  col(col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12): FieldBuilder;
  name(name: string): FieldBuilder;
  label(label: string): FieldBuilder;
  width(width: number | string): FieldBuilder;
  height(height: number): FieldBuilder;
  minWidth(minWidth: number): FieldBuilder;
  minHeight(minHeight: number): FieldBuilder;
  align(align: 'left' | 'center' | 'right'): FieldBuilder;
  sortable(sortable: boolean): FieldBuilder;
  filterable(filterable: boolean): FieldBuilder;
  hideable(hideable: boolean): FieldBuilder;
  validators(validators: ValidationRule[]): FieldBuilder;
  mode(mode: 'remote' | 'local' | 'none'): FieldBuilder;
  url(url: string): FieldBuilder;
  loadOptions(param: LoadOption[]): FieldBuilder;
  filters(filters: FilterRule[]): FieldBuilder;
  byKeyUrl(byKeyUrl: string): FieldBuilder;
  textField(textField: string): FieldBuilder;
  valueField(valueField: string): FieldBuilder;
  valueType(valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime'): FieldBuilder;
  selectedFilterOperation(operation: '<' | '<=' | '<>' | '=' | '>' | '>=' | 'between' | 'contains' | 'endswith' | 'notcontains' | 'startswith'): FieldBuilder;
  filterValue(filterValue: string | number | boolean | Date | null | undefined): FieldBuilder;
  data(data: DataRecord[]): FieldBuilder;
  format(format: string): FieldBuilder;
  formatter(formatter: FormatterFn): FieldBuilder;
  itemFormatter(itemFormatter: ItemFormatterFn): FieldBuilder;
  visible(visible: boolean): FieldBuilder;
  defaultValue(defaultValue: string | number | boolean | Date | null | undefined | (() => unknown)): FieldBuilder;
  onChange(onChange: OnChangeFn): FieldBuilder;
  onSelect(onSelect: (e: unknown) => void): FieldBuilder;
  onClick(onClick: (e: unknown) => void): FieldBuilder;
  autoSelectIfSingle(value: boolean): FieldBuilder;
  readonly(readonly: boolean): FieldBuilder;
  disabled(disabled: boolean): FieldBuilder;
  hidden(hidden: boolean): FieldBuilder;
  required(required: boolean): FieldBuilder;
  precision(value: number): FieldBuilder;
  accept(value: string | null | undefined): FieldBuilder;
  buttons(value: DataRecord[]): FieldBuilder;
  hidingPriority(value: number): FieldBuilder;
  searchEnabled(value: boolean): FieldBuilder;
  searchExpr(value: string[]): FieldBuilder;
  fixed(value: boolean): FieldBuilder;
  fixedPosition(value: 'left' | 'right'): FieldBuilder;
  helpText(value: string): this;
  contentRender(value: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined): this;
  visibleOnForm(value: boolean): this;
  onKeyPress(param: (e: KeyboardEvent) => void): this;
  multiple(value: boolean): FieldBuilder;
  editorOptions(param: DataRecord): this;
  sendAsString(value: boolean): FieldBuilder;
  maxLength(value: number): FieldBuilder;
  build(): Field;
}
//#endregion
//#region assets/core/data/GridData.d.ts
interface GridData<T> {
  data: T[];
  totalCount: number;
  summary: T[] | null;
}
//#endregion
//#region assets/core/http/CoreHttpClient.d.ts
type CoreResponseType = 'json' | 'arraybuffer' | 'blob' | 'text';
interface CoreRequestConfig {
  params?: DataRecord;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  responseType?: CoreResponseType;
}
interface CoreHttpResponse<T> {
  response: Response;
  data: T;
  headers: Headers;
  status: number;
}
interface CoreHttpErrorData {
  detail?: string;
  message?: string;
  violations?: unknown;
  [key: string]: unknown;
}
interface CoreHttpError extends Error {
  status?: number;
  data?: CoreHttpErrorData;
}
interface CoreHttpClientConfig {
  baseUrl?: string;
  credentials?: RequestCredentials;
  locale?: string;
  refreshPath?: string;
  loginPath?: string;
  onUnauthorized?: (error: CoreHttpError) => void;
  onError?: (error: CoreHttpError) => void;
}
declare class CoreHttpClient {
  private readonly config;
  private refreshPromise;
  constructor(config?: CoreHttpClientConfig);
  private headers;
  private refreshSession;
  private safeErrorData;
  private request;
  get<T>(url: string, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  post<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  put<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  patch<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  delete<T>(url: string, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
}
declare function createCoreHttpClient(config?: CoreHttpClientConfig): CoreHttpClient;
//#endregion
//#region assets/core/http/CoreHttpContext.d.ts
interface CoreHttpProviderProps {
  children: React$1.ReactNode;
  client?: CoreHttpClient;
  config?: CoreHttpClientConfig;
}
declare function CoreHttpProvider({
  children,
  client,
  config
}: CoreHttpProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useCoreHttpClient(): CoreHttpClient;
//#endregion
//#region assets/core/data/ApiPlatformRemoteDataSource.d.ts
type RemoteFilterDescriptor = unknown[];
type RemoteSortDescriptor = {
  selector?: string;
  desc?: boolean;
} | string;
type RemoteLoadOptions = DataRecord & {
  filter?: RemoteFilterDescriptor;
  sort?: RemoteSortDescriptor[];
  appendData?: DataRecord[];
  prependData?: DataRecord[];
  searchExpr?: unknown;
  searchValue?: unknown;
  searchOperation?: unknown;
};
type RemoteDataSourceOptions = {
  url: string;
  idField: string;
  byKeyUrl?: string | null;
  defaultFilterRules?: RemoteFilterDescriptor[];
  defaultSortRules?: RemoteSortDescriptor[];
  options?: LoadOption[];
  iriMode?: boolean;
  httpClient?: CoreHttpClient;
};
declare class ApiPlatformRemoteDataSource {
  private readonly config;
  private readonly httpClient;
  constructor(config: RemoteDataSourceOptions);
  makeFilterRules(filterRules: FilterRule[]): string;
  prepareLoadOptions(loadOptions: RemoteLoadOptions): RemoteLoadOptions;
  load(loadOptions: RemoteLoadOptions): Promise<GridData<DataRecord>>;
  byKey(key: unknown): Promise<DataRecord | null>;
  private fetchAll;
  private fetchById;
}
//#endregion
//#region assets/core/date/DateUtils.d.ts
declare const LIMA_TZ = "America/Lima";
declare class DateUtils {
  static dateFormatter(date: Date): string;
  static dateParser(dateString: string): Date;
  static format(dateString: string, formatOptions?: Intl.DateTimeFormatOptions): string;
  static addDays(fieldValue: string | number | Date, days: number): Date;
  static addMonths(fieldValue: string | number | Date, months: number): Date;
  static addYears(fieldValue: string | number | Date, years: number): Date;
  static dayDiff(startDate: string | number | Date, endDate: string | number | Date): number;
  static monthDiff(startDate: string | number | Date, endDate: string | number | Date): number;
}
//#endregion
//#region assets/core/config/CoreConfig.d.ts
interface CoreConfig {
  locale: string;
  timezone: string;
}
declare function configureCoreDate(config: Partial<CoreConfig>): void;
declare function getCoreLocale(): string;
declare function getCoreTimezone(): string;
interface CoreConfigProviderProps {
  locale: string;
  timezone: string;
  children: React$1.ReactNode;
}
declare const CoreConfigProvider: ({
  locale,
  timezone,
  children
}: CoreConfigProviderProps) => import("react/jsx-runtime").JSX.Element;
declare function useCoreConfig(): CoreConfig;
//#endregion
//#region assets/core/components/ConfirmDialog.d.ts
interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
}
declare function ConfirmDialog({
  open,
  title,
  message,
  onConfirm,
  onCancel,
  confirmLabel,
  cancelLabel,
  variant
}: ConfirmDialogProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/ui/app-dialog/AppDialog.d.ts
interface AppDialogProps {
  visible?: boolean;
  open?: boolean;
  title: string;
  onClose: () => void;
  children: React$1.ReactNode;
  footer?: React$1.ReactNode;
  width?: number | string;
  maxWidth?: number | string;
  height?: number | string;
  fullScreen?: boolean;
  showCloseButton?: boolean;
  closeOnOutsideClick?: boolean;
  closeOnEscape?: boolean;
  restoreFocus?: boolean;
  keepMounted?: boolean;
  rootClassName?: string;
  className?: string;
  headerClassName?: string;
  titleClassName?: string;
  bodyClassName?: string;
  footerClassName?: string;
  role?: string;
}
declare const AppDialog: ({
  visible,
  open,
  title,
  onClose,
  children,
  footer,
  width,
  maxWidth,
  height,
  fullScreen,
  showCloseButton,
  closeOnOutsideClick,
  closeOnEscape,
  restoreFocus,
  keepMounted,
  rootClassName,
  className,
  headerClassName,
  titleClassName,
  bodyClassName,
  footerClassName,
  role
}: AppDialogProps) => React$1.ReactPortal | null;
//#endregion
//#region assets/core/datagrid/DataGridEvents.d.ts
declare const DATA_GRID_EVENTS: {
  SELECTION_CHANGED: string;
  TOOLBAR_EVENT: string;
};
//#endregion
//#region assets/core/datagrid/GridHandle.d.ts
interface GridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refresh: () => void;
  reset: () => void;
  loadData: () => Promise<unknown>;
  getSelectedRowKey: () => string | number | undefined;
  getSelectedRow: () => DataRecord | undefined;
  getSelectedRowKeys: () => unknown[];
  getSelectedRows: () => DataRecord[];
  getFilter: () => unknown[];
  filter: (filterRule: FilterRule | null) => void;
}
//#endregion
//#region assets/core/datagrid/DataGridViewOptions.d.ts
interface DataGridSelectionChangedEvent {
  selectedRowsData?: DataRecord[];
}
interface DataGridSummaryItem {
  column?: string;
  summaryType?: string;
  displayFormat?: string;
  valueFormat?: string;
  customizeText?: (cellInfo: unknown) => string;
}
interface DataGridViewOptions {
  id: string;
  title: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[] | ((parentRow: DataRecord) => Field[]);
  toolbar?: ToolbarButtonItem[] | ToolbarButtonItem[][];
  events?: DataGridEventNames;
  allowAdd?: boolean;
  allowEdit?: boolean;
  allowDelete?: boolean;
  allowExport?: boolean;
  addDisabled?: boolean;
  editDisabled?: boolean;
  deleteDisabled?: boolean;
  summaryFields?: DataGridSummaryItem[];
  filter?: FilterRule[];
  sort?: Array<{
    selector: string;
    desc: boolean;
  }>;
  mode?: 'minimal' | 'normal' | 'full';
  paging?: boolean;
  pageSize?: number;
  hasBackButton?: boolean;
  columnsHidingEnabled?: boolean;
  stateStoringEnabled?: boolean;
  toolbarVisible?: boolean;
  selectionMode?: 'single' | 'multiple';
  inlineActions?: boolean;
  onBack?: () => void;
  onSelectionChanged?: (event: DataGridSelectionChangedEvent) => void;
  onRowPrepared?: (event: unknown) => void;
  onContentReady?: () => void;
  onFilterChange?: (filters: Record<string, string>) => void;
  filterRow?: boolean;
  headerFilter?: boolean;
  manualLoad?: boolean;
  beforeToolbar?: () => ReactNode;
  editMode?: 'popup' | 'row' | 'cell' | 'batch';
  visibleColumns?: string[] | null;
}
//#endregion
//#region assets/core/datagrid/NativeDataGridView.d.ts
declare const NativeDataGridView: React$1.ForwardRefExoticComponent<DataGridViewOptions & React$1.RefAttributes<GridHandle>>;
//#endregion
//#region assets/core/dialog/CrudDialogView.d.ts
interface CrudDialogViewOptions {
  title?: string;
  width?: number;
  height?: number;
  maxHeight?: number;
  visible?: boolean;
  events?: DialogEventNames;
  children?: React$1.ReactNode;
  positiveText?: string;
  negativeText?: string;
  footerVisible?: boolean;
  fitContent?: boolean;
  isOpen?: boolean;
  onClose?: () => void;
}
interface CrudDialogViewEvents {
  open: (title?: string) => void;
  close: () => void;
}
declare const CrudDialogView: React$1.ForwardRefExoticComponent<CrudDialogViewOptions & React$1.RefAttributes<CrudDialogViewEvents>>;
//#endregion
//#region assets/core/form/FormViewOptions.d.ts
interface FormViewOptions {
  className?: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  detailPropertyName?: string;
  allowAdding?: boolean;
  allowUpdating?: boolean;
  allowDeleting?: boolean;
  events?: FormEventNames;
  editable?: boolean;
  format?: 'json' | 'multipart';
  requiredDetail?: boolean;
  /** @deprecated no tiene efecto, IRI siempre se aplica. */
  useIRI?: boolean;
  /** Called after a successful POST or PATCH. Receives the server response. */
  onSaveSuccess?: (response: unknown) => void;
  /** Called when POST or PATCH fails after HTTP error handling. */
  onSaveError?: (error?: unknown) => void;
  /** Called after a successful DELETE. */
  onDeleteSuccess?: (response: unknown) => void;
  /** Called when DELETE fails. */
  onDeleteError?: (error?: unknown) => void;
  /** Called whenever the loading state changes. */
  onLoadingChange?: (loading: boolean) => void;
  /** Optional layout descriptor for tabs or collapsible sections. */
  formLayout?: FormLayout;
  /** Called whenever any form field value changes with a full form data snapshot. */
  onFieldDataChanged?: FormDataChangedHandler;
  /** Latest runtime-computed values pushed down by SmartCrudPage. */
  computedValues?: FormDataSnapshot;
  /** @deprecated no longer used, sidebar is always single column. */
  sidebarColCount?: never;
}
//#endregion
//#region assets/core/form/NativeFormView.d.ts
declare const NativeFormView: React$1.ForwardRefExoticComponent<FormViewOptions & React$1.RefAttributes<FormHandle>>;
//#endregion
//#region assets/core/form/FormEvents.d.ts
declare const FORM_EVENTS: {
  FIELD_CHANGED: string;
  ROW_ADDED: string;
  ROW_REMOVED: string;
  ROW_UPDATED: string;
  LOADING: string;
  LOADING_DETAIL: string;
};
//#endregion
//#region assets/core/event/EventHook.d.ts
type Unsubscribe = () => void;
/** Minimal subscription token — compatible with RxJS Subscription.unsubscribe() call-sites */
interface EventSubscription {
  unsubscribe: Unsubscribe;
  /** Convenience alias used by some call-sites via .add() — no-op, kept for compat */
  add: (teardown: Unsubscribe) => EventSubscription;
}
declare const useEvents: () => readonly [<T>(name: string, handler: (payload: T) => void) => EventSubscription, <T>(name: string, payload?: T) => void];
declare const dispatch: <T>(name: string, payload?: T) => void;
//#endregion
//#region assets/core/event/createCrudEvents.d.ts
/**
 * Generic factory that builds a typed set of CRUD event name strings for a
 * given `prefix`.  The returned object is `const`-asserted so every value is
 * a template-literal type — callers get full type inference without having to
 * cast anything.
 *
 * This helper belongs in `core/` because it has no feature-specific logic; it
 * is used by both the core event bus and by every feature module that needs
 * its own event namespace.
 *
 * @example
 * const productEvents = createCrudEvents('product');
 * // productEvents.ADD === 'product:add'
 */
declare const createCrudEvents: <TPrefix extends string>(prefix: TPrefix) => {
  readonly ADD: `${TPrefix}:add`;
  readonly EDIT: `${TPrefix}:edit`;
  readonly DELETE: `${TPrefix}:delete`;
  readonly SAVE: `${TPrefix}:save`;
  readonly CANCEL: `${TPrefix}:cancel`;
  readonly SUCCESS: `${TPrefix}:success`;
  readonly LOADING: `${TPrefix}:loading`;
};
//#endregion
//#region assets/core/event/domainEvents.d.ts
declare const branchEvents: {
  readonly ADD: "branch:add";
  readonly EDIT: "branch:edit";
  readonly DELETE: "branch:delete";
  readonly SAVE: "branch:save";
  readonly CANCEL: "branch:cancel";
  readonly SUCCESS: "branch:success";
  readonly LOADING: "branch:loading";
};
declare const categoryEvents: {
  readonly ADD: "category:add";
  readonly EDIT: "category:edit";
  readonly DELETE: "category:delete";
  readonly SAVE: "category:save";
  readonly CANCEL: "category:cancel";
  readonly SUCCESS: "category:success";
  readonly LOADING: "category:loading";
};
declare const companyEvents: {
  readonly ADD: "company:add";
  readonly EDIT: "company:edit";
  readonly DELETE: "company:delete";
  readonly SAVE: "company:save";
  readonly CANCEL: "company:cancel";
  readonly SUCCESS: "company:success";
  readonly LOADING: "company:loading";
};
declare const inboundEvents: {
  readonly ADD: "inbound:add";
  readonly EDIT: "inbound:edit";
  readonly DELETE: "inbound:delete";
  readonly SAVE: "inbound:save";
  readonly CANCEL: "inbound:cancel";
  readonly SUCCESS: "inbound:success";
  readonly LOADING: "inbound:loading";
};
declare const operationEvents: {
  readonly ADD: "operation:add";
  readonly EDIT: "operation:edit";
  readonly DELETE: "operation:delete";
  readonly SAVE: "operation:save";
  readonly CANCEL: "operation:cancel";
  readonly SUCCESS: "operation:success";
  readonly LOADING: "operation:loading";
};
declare const paymentMethodEvents: {
  readonly ADD: "payment-methods:add";
  readonly EDIT: "payment-methods:edit";
  readonly DELETE: "payment-methods:delete";
  readonly SAVE: "payment-methods:save";
  readonly CANCEL: "payment-methods:cancel";
  readonly SUCCESS: "payment-methods:success";
  readonly LOADING: "payment-methods:loading";
};
declare const permissionEvents: {
  readonly ADD: "permission:add";
  readonly EDIT: "permission:edit";
  readonly DELETE: "permission:delete";
  readonly SAVE: "permission:save";
  readonly CANCEL: "permission:cancel";
  readonly SUCCESS: "permission:success";
  readonly LOADING: "permission:loading";
};
declare const priceEvents: {
  readonly ADD: "price:add";
  readonly EDIT: "price:edit";
  readonly DELETE: "price:delete";
  readonly SAVE: "price:save";
  readonly CANCEL: "price:cancel";
  readonly SUCCESS: "price:success";
  readonly LOADING: "price:loading";
};
declare const priceListEvents: {
  readonly ADD: "price-list:add";
  readonly EDIT: "price-list:edit";
  readonly DELETE: "price-list:delete";
  readonly SAVE: "price-list:save";
  readonly CANCEL: "price-list:cancel";
  readonly SUCCESS: "price-list:success";
  readonly LOADING: "price-list:loading";
};
declare const roleEvents: {
  readonly ADD: "role:add";
  readonly EDIT: "role:edit";
  readonly DELETE: "role:delete";
  readonly SAVE: "role:save";
  readonly CANCEL: "role:cancel";
  readonly SUCCESS: "role:success";
  readonly LOADING: "role:loading";
};
declare const sunatCatalogEvents: {
  readonly ADD: "sunat-catalog:add";
  readonly EDIT: "sunat-catalog:edit";
  readonly DELETE: "sunat-catalog:delete";
  readonly SAVE: "sunat-catalog:save";
  readonly CANCEL: "sunat-catalog:cancel";
  readonly SUCCESS: "sunat-catalog:success";
  readonly LOADING: "sunat-catalog:loading";
};
declare const supplierEvents: {
  readonly ADD: "supplier:add";
  readonly EDIT: "supplier:edit";
  readonly DELETE: "supplier:delete";
  readonly SAVE: "supplier:save";
  readonly CANCEL: "supplier:cancel";
  readonly SUCCESS: "supplier:success";
  readonly LOADING: "supplier:loading";
};
declare const unitOfMeasureEvents: {
  readonly ADD: "unit-of-measure:add";
  readonly EDIT: "unit-of-measure:edit";
  readonly DELETE: "unit-of-measure:delete";
  readonly SAVE: "unit-of-measure:save";
  readonly CANCEL: "unit-of-measure:cancel";
  readonly SUCCESS: "unit-of-measure:success";
  readonly LOADING: "unit-of-measure:loading";
};
declare const userEvents: {
  readonly ADD: "user:add";
  readonly EDIT: "user:edit";
  readonly DELETE: "user:delete";
  readonly SAVE: "user:save";
  readonly CANCEL: "user:cancel";
  readonly SUCCESS: "user:success";
  readonly LOADING: "user:loading";
};
declare const voucherSeriesEvents: {
  readonly ADD: "voucher-series:add";
  readonly EDIT: "voucher-series:edit";
  readonly DELETE: "voucher-series:delete";
  readonly SAVE: "voucher-series:save";
  readonly CANCEL: "voucher-series:cancel";
  readonly SUCCESS: "voucher-series:success";
  readonly LOADING: "voucher-series:loading";
};
declare const voucherTypeEvents: {
  readonly ADD: "voucherType:add";
  readonly EDIT: "voucherType:edit";
  readonly DELETE: "voucherType:delete";
  readonly SAVE: "voucherType:save";
  readonly CANCEL: "voucherType:cancel";
  readonly SUCCESS: "voucherType:success";
  readonly LOADING: "voucherType:loading";
};
declare const warehouseEvents: {
  readonly ADD: "warehouse:add";
  readonly EDIT: "warehouse:edit";
  readonly DELETE: "warehouse:delete";
  readonly SAVE: "warehouse:save";
  readonly CANCEL: "warehouse:cancel";
  readonly SUCCESS: "warehouse:success";
  readonly LOADING: "warehouse:loading";
};
//#endregion
//#region assets/core/runtime/CoreRuntimeContext.d.ts
type CoreNotificationType = 'success' | 'error' | 'warning' | 'info';
interface CoreRuntime {
  notify: (message: string, type?: CoreNotificationType, durationMs?: number) => void;
  confirm: (message: string) => boolean;
}
interface CoreRuntimeProviderProps {
  children: React$1.ReactNode;
  runtime?: Partial<CoreRuntime>;
}
declare function CoreRuntimeProvider({
  children,
  runtime
}: CoreRuntimeProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useCoreRuntime(): CoreRuntime;
//#endregion
//#region assets/core/provider/CoreProvider.d.ts
interface CoreProviderProps {
  children: React$1.ReactNode;
  http?: CoreHttpClientConfig;
  httpClient?: CoreHttpClient;
  runtime?: Partial<CoreRuntime>;
}
declare function CoreProvider({
  children,
  http,
  httpClient,
  runtime
}: CoreProviderProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/mercure/MercureProvider.d.ts
interface MercureProviderProps {
  /** Hub URL discovered from the `Link` header, or null if unavailable. */
  hubUrl: string | null;
  children: ReactNode;
}
/**
 * Provides the Mercure hub URL to the React tree and keeps `MercureManager`
 * in sync whenever the URL changes.
 */
declare function MercureProvider({
  hubUrl,
  children
}: MercureProviderProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/mercure/useMercureHub.d.ts
/**
 * useMercureHub — Hook that returns the Mercure hub URL from context.
 *
 * Returns `null` if:
 * - The component is rendered outside a `<MercureProvider>` (default context value).
 * - The hub URL has not been discovered yet (header `Link` not received).
 * - Mercure is not configured in the backend.
 *
 * This hook does NOT perform any fetch or side effect — it only reads from context.
 * Hub discovery is handled by the Axios response interceptor registered in EfactApp.tsx.
 *
 * ## Usage
 *
 * ```tsx
 * const hubUrl = useMercureHub();
 * // hubUrl is string | null
 * ```
 */
/**
 * Returns the Mercure hub URL from the nearest `<MercureProvider>`,
 * or `null` if the hub is not available.
 */
declare function useMercureHub(): string | null;
//#endregion
//#region assets/core/mercure/useMercureSubscription.d.ts
/**
 * useMercureSubscription — Hook that subscribes to a Mercure topic and calls
 * a callback whenever an SSE message is received for that topic.
 *
 * ## Behaviour
 * - If `hubUrl` is null (Mercure not configured) or `enabled` is false → no-op.
 * - Subscribes to the wildcard topic `<origin>/<apiUrl>/{id}` (URI Template RFC 6570),
 *   which captures any item-level event (create / update / delete) for the collection.
 * - Cleanup: unsubscribes on unmount or when dependencies change (no memory leaks).
 *
 * ## Usage
 *
 * ```tsx
 * useMercureSubscription(
 *   resource.apiUrl,
 *   () => { gridRef.current?.instance().refresh(); },
 *   resource.mercure !== false,
 * );
 * ```
 *
 * @param apiUrl   The resource API URL (e.g. `'api/products'` or `'/api/products'`).
 *                 Used to build the wildcard topic URI.
 * @param onUpdate Callback invoked on every SSE message for the topic.
 * @param enabled  When false, the subscription is skipped entirely. Defaults to true.
 */
declare function useMercureSubscription(apiUrl: string | undefined, onUpdate: () => void, enabled?: boolean): void;
//#endregion
//#region assets/core/mercure/MercureManager.d.ts
/**
 * MercureManager — Singleton that manages the lifecycle of EventSource connections.
 *
 * Inspired by the `manager.ts` pattern from `api-platform/admin`, adapted for this project:
 * - Ref-counting per topic: multiple subscribers to the same topic share one EventSource.
 * - Graceful degradation: if hubUrl is null, subscribe() is a no-op.
 * - Topic format: full URI or URI Template (RFC 6570), e.g. `https://host/api/products/{id}`.
 *   The hub receives it as a query param: `?topic=<encoded-topic>`.
 */
declare class MercureManager {
  private hubUrl;
  private readonly topics;
  private readonly hubUrlListeners;
  /**
   * Update the hub URL (called by the Axios interceptor when the `Link` header is discovered,
   * or by MercureProvider). Notifies all registered listeners.
   *
   * Idempotent: if the URL is already set to the same value, listeners are NOT re-notified.
   */
  setHubUrl(url: string | null): void;
  getHubUrl(): string | null;
  /**
   * Register a callback that fires whenever the hub URL changes.
   *
   * Returns an unsubscribe function — call it in a `useEffect` cleanup to avoid memory leaks.
   *
   * @example
   * ```tsx
   * useEffect(() => {
   *   return MercureManager.onHubUrlChange(setHubUrl);
   * }, []);
   * ```
   */
  onHubUrlChange(callback: (url: string | null) => void): () => void;
  /**
   * Subscribe to a Mercure topic.
   *
   * @param topic    Full topic URI or URI Template, e.g. `https://host/api/products/{id}`.
   * @param callback Called with the parsed JSON payload on each SSE message.
   *
   * If `hubUrl` is null (hub not configured), this is a no-op (graceful degradation).
   * If an EventSource for this topic already exists, it is reused (ref-counting).
   */
  subscribe(topic: string, callback: (data: unknown) => void): void;
  /**
   * Unsubscribe a specific callback from a topic.
   *
   * Decrements the ref count. When count reaches 0, the EventSource is closed
   * and removed from the internal map.
   */
  unsubscribe(topic: string, callback: (data: unknown) => void): void;
  /**
   * Close all active EventSource connections and clear the topic map.
   *
   * Called internally when the hub URL changes so stale connections
   * bound to the previous hub are not leaked.
   */
  disconnectAll(): void;
}
/** Singleton instance — shared across the entire application. */
declare const mercureManager: MercureManager;
//#endregion
export { ApiPlatformRemoteDataSource, AppDialog, type AppDialogProps, type AuditEntry, type AuditTrailConfig, type BulkAction, type ColumnPreset, ConfirmDialog, type ConfirmDialogProps, type CoreConfig, CoreConfigProvider, type CoreConfigProviderProps, CoreHttpClient, type CoreHttpClientConfig, type CoreHttpError, type CoreHttpErrorData, CoreHttpProvider, type CoreHttpProviderProps, type CoreHttpResponse, type CoreNotificationType, CoreProvider, type CoreProviderProps, type CoreRequestConfig, type CoreResponseType, type CoreRuntime, CoreRuntimeProvider, type CoreRuntimeProviderProps, CrudPage, DATA_GRID_EVENTS, type DataGridSelectionChangedEvent, type DataGridSummaryItem, NativeDataGridView as DataGridView, type DataGridViewOptions, type DataRecord, DateUtils, CrudDialogView as DialogView, type EnumOption, type EventSubscription, FORM_EVENTS, type Field, FieldBuilder, type FieldDef, type FieldOverride, FieldType, type FilterRule, type FormHandle, type FormLayout, type FormOnChangeFn, type FormSection, type FormTab, NativeFormView as FormView, type FormatterFn, type GridCellContext, type GridData, type GridHandle, type GridOnChangeFn, type ItemFormatterFn, LIMA_TZ, type LoadOption, mercureManager as MercureManager, type MercureManager as MercureManagerType, MercureProvider, type MercureProviderProps, type OnChangeFn, type RemoteDataSourceOptions, type RemoteFilterDescriptor, type RemoteLoadOptions, type RemoteSortDescriptor, type ResolvedPermissions, type ResourceConfig, type ResourcePermissions, type ResourceRouting, type SelectionState, type SmartCrudFieldContract, type SmartCrudFieldOperation, type SmartCrudFieldOperationBehavior, type SmartCrudFieldOperationFlag, type SmartCrudFieldOperationState, type SmartCrudFieldPatch, type SmartCrudFieldResolutionStrategy, type SmartCrudFieldSourceMode, type SmartCrudHydraFieldContract, type SmartCrudHydraFieldDirective, type SmartCrudManualField, type SmartCrudManualFieldContract, type SmartCrudModelFieldKey, type SmartCrudOperation, SmartCrudPage, type SmartCrudRecord, SmartCrudRolesProvider, type ValidationRule, branchEvents, categoryEvents, checkboxField, companyEvents, configureCoreDate, createCoreHttpClient, createCrudEvents, createScopedEventBus, currencyField, dateField, datetimeField, defineFieldContract, defineFields, defineResource, dispatch, entityField, enumField, getCoreLocale, getCoreTimezone, identityField, imageField, inboundEvents, initCoreI18n, noneField, numberField, operationEvents, passwordField, paymentMethodEvents, permissionEvents, priceEvents, priceListEvents, resolveSmartCrudFields, roleEvents, selectField, sunatCatalogEvents, supplierEvents, switchField, textField, textareaField, unitOfMeasureEvents, useCoreConfig, useCoreHttpClient, useCoreRuntime, useCoreTranslation, useCrudPage, useEvents, useMercureHub, useMercureSubscription, usePermissions, useSelectionState, useSmartCrudRoles, userEvents, voucherSeriesEvents, voucherTypeEvents, warehouseEvents };