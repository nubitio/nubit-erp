import React$1, { ReactNode, RefObject } from "react";
import { CoreHttpClient, DataRecord, DataRecord as DataRecord$1, GridData, GridData as GridData$1 } from "@nubit/core";
import { AppDialog, AppDialogProps, ConfirmDialog, ConfirmDialogProps } from "@nubit/ui";

//#region assets/crud/field/FieldType.d.ts
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
//#region assets/crud/field/FilterRule.d.ts
interface FilterRule {
  field: string;
  operator: string;
  value: string | number | boolean;
}
//#endregion
//#region assets/crud/field/ValidationRule.d.ts
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
//#region assets/crud/field/FieldCallbacks.d.ts
interface GridCellContext<TData extends DataRecord$1 = Record<string, any>, TValue = any> {
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
type GridOnChangeFn = (newData: DataRecord$1, value: unknown, currentRowData: DataRecord$1) => void | Promise<void>;
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
//#region assets/crud/field/Field.d.ts
type LoadOption = Record<string, any>;
interface FieldButton {
  name?: string;
  location?: string;
  options?: {
    icon?: string;
    stylingMode?: string;
    onClick?: (event: unknown, form: {
      getFieldValue: (name: string) => unknown;
      setFieldValue: (name: string, value: unknown) => void;
    }) => void;
  };
}
interface Field {
  isIdentity: boolean;
  type: FieldType;
  col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | undefined;
  name: string;
  label: string;
  width: string | number | undefined;
  height: number | null;
  minWidth: number | undefined;
  align: 'left' | 'center' | 'right' | undefined;
  sortable: boolean;
  filterable: boolean;
  hideable: boolean;
  validators: ValidationRule[];
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
  data: DataRecord$1[];
  formatter: FormatterFn | undefined;
  itemFormatter: ItemFormatterFn | undefined;
  visible: boolean;
  defaultValue: string | number | boolean | Date | null | undefined | (() => unknown);
  autoSelectIfSingle?: boolean;
  onChange: OnChangeFn | undefined;
  onSelect: ((e: unknown) => void) | undefined;
  onClick: ((e: unknown) => void) | undefined;
  readonly: boolean;
  disabled: boolean;
  hidden: boolean;
  required: boolean;
  precision: number;
  accept: string | null | undefined;
  buttons: FieldButton[];
  searchEnabled: boolean;
  searchExpr: string[] | null;
  helpText: string | undefined;
  contentRender: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined;
  visibleOnForm: boolean;
  maxLength: number | undefined;
  multiple: boolean;
  sendAsString: boolean;
  /** Column display order hint set via `x-crud.order` from Hydra metadata. */
  order?: number;
  /** Returns true when the field should be visible */
  visibleWhen?: (formData: DataRecord$1) => boolean;
  /** Returns true when the field should be disabled (read-only in form) */
  disabledWhen?: (formData: DataRecord$1) => boolean;
  /** Derived value — field is rendered as read-only with this computed value */
  computed?: (formData: DataRecord$1) => unknown;
  /** Default value applied when the current value is empty for the current form data */
  defaultWhen?: (formData: DataRecord$1) => unknown;
  /** Returns true when the field should be required for the current form data */
  requiredWhen?: (formData: DataRecord$1) => boolean;
  /** Clears the field value when `visibleWhen` hides it. */
  clearWhenHidden?: boolean;
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
type FieldDef<T extends DataRecord$1 = DataRecord$1> = Omit<Field, 'defaultValue' | 'onChange'> & {
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
//#region assets/crud/form/FormDataSnapshot.d.ts
type FormDataRecord = DataRecord$1;
type FormDataSnapshot = FormDataRecord;
type FormDataChangedHandler = (formData: FormDataSnapshot) => void;
//#endregion
//#region assets/crud/form/FormHandle.d.ts
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
//#region assets/crud/datagrid/GridHandle.d.ts
interface GridHandle {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refresh: () => void;
  reset: () => void;
  loadData: () => Promise<unknown>;
  getSelectedRowKey: () => string | number | undefined;
  getSelectedRow: () => DataRecord$1 | undefined;
  getSelectedRowKeys: () => unknown[];
  getSelectedRows: () => DataRecord$1[];
  getFilter: () => unknown[];
  filter: (filterRule: FilterRule | null) => void;
}
//#endregion
//#region assets/crud/form/FormLayout.d.ts
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
//#region assets/crud/crud/BulkAction.d.ts
interface BulkAction {
  key: string;
  label: string;
  icon?: string;
  confirmMessage?: string;
  onAction: (selectedIds: (string | number)[]) => void | Promise<void>;
}
//#endregion
//#region assets/crud/crud/ColumnPreset.d.ts
interface ColumnPreset {
  key: string;
  label: string;
  columns: string[];
}
//#endregion
//#region assets/crud/crud/AuditTrail.d.ts
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
//#region assets/crud/crud/fieldOperationSemantics.d.ts
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
//#region assets/crud/crud/fieldContract.d.ts
type SmartCrudRecord = object;
type SmartCrudModelFieldKey<T extends SmartCrudRecord> = Extract<keyof T, string>;
type SmartCrudFieldRecord<T extends SmartCrudRecord> = T & DataRecord$1;
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
//#region assets/crud/adapter/BackendAdapter.d.ts
/**
 * Strategy object that abstracts backend-specific conventions from the CRUD engine.
 *
 * The engine ships with two built-in adapters:
 *   - `HydraAdapter`  — for API Platform / JSON-LD backends (default)
 *   - `RestAdapter`   — for plain OpenAPI / REST backends
 *
 * Provide a custom implementation on `ResourceConfig.adapter` to support any other backend.
 */
interface BackendAdapter {
  /**
   * Extract the unique identifier from a data record.
   * Used by the grid to identify rows for edit/delete actions.
   *
   * @param record  - The data record returned by the API.
   * @param idField - The name of the identity field declared in the field list.
   */
  getRowId(record: DataRecord$1, idField: string): string | number;
  /**
   * Build the URL for a single-item operation (PATCH / DELETE).
   *
   * @param baseUrl - The collection URL from ResourceConfig.apiUrl.
   * @param id      - The value returned by getRowId().
   */
  buildItemUrl(baseUrl: string, id: string | number): string;
  /**
   * Serialize a single entity field value for API submission (POST / PATCH body).
   *
   * @param field    - The ENTITY field definition.
   * @param rawValue - The raw value stored in the form (object, IRI string, or scalar id).
   * @returns The serialized value to place in the request body, or undefined to omit the field.
   */
  serializeEntityRef(field: Field, rawValue: unknown): unknown;
  /**
   * Normalize an entity field value received from the API into the scalar key
   * expected by the form's select editor.
   *
   * @param rawValue - The raw value in the API response (IRI string, nested object, or scalar).
   * @param field    - The ENTITY field definition.
   * @returns The normalized scalar key (e.g. id number, IRI string, or the object unchanged for inline display).
   */
  normalizeEntityValue(rawValue: unknown, field: Field): unknown;
  /**
   * Extract the display key from an option record for entity select fields.
   * Used when matching a stored value against a list of loaded options.
   *
   * @param item  - An option record from the remote data source.
   * @param field - The ENTITY field definition.
   */
  getEntityOptionKey(item: DataRecord$1, field: Field): unknown;
  /**
   * Parse the collection response body into an items array and total count.
   * Used by the grid data source.
   *
   * @param response - The raw API response body.
   */
  parseListResponse(response: unknown): {
    items: DataRecord$1[];
    total: number;
  };
  /**
   * Synthesize a canonical IRI / key for an entity object that arrived without one.
   * Called during form data normalization when the object lacks the expected identity key.
   * Return undefined if synthesis is not possible.
   *
   * @param field       - The ENTITY field definition.
   * @param entityValue - The entity object from the API response.
   */
  synthesizeEntityKey(field: Field, entityValue: FormDataRecord): string | undefined;
}
//#endregion
//#region assets/crud/crud/ResourceConfig.d.ts
/**
 * Generic factory that receives a form ref and returns typed field definitions.
 * Defaults to `DataRecord` for backward compatibility.
 */
type FieldsFactory<T extends DataRecord$1 = DataRecord$1> = (formRef: RefObject<FormHandle | null>) => FieldDef<T>[];
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
type ResourceToolbarItems = ToolbarButtonItem[] | ToolbarButtonItem[][];
interface ResourceToolbarContext<T extends DataRecord$1 = DataRecord$1> {
  resource: ResourceConfig<T>;
  selectedRow: T | undefined;
  selectedRows: T[];
  gridRef: RefObject<GridHandle | null>;
  formRef: RefObject<FormHandle | null>;
  events: FormEventNames;
  emit: <P>(name: string, payload?: P) => void;
}
type ResourceToolbar<T extends DataRecord$1 = DataRecord$1> = ResourceToolbarItems | ((context: ResourceToolbarContext<T>) => ResourceToolbarItems);
interface ResourceGridDetail {
  url: string;
  fields: Field[] | ((parentRow: DataRecord$1) => Field[]);
}
interface ResourceFormDetail {
  url?: string;
  fields: Field[];
  propertyName?: string;
  allowAdding?: boolean;
  allowDeleting?: boolean;
  allowUpdating?: boolean;
  required?: boolean;
}
interface ResourceConfig<T extends DataRecord$1 = DataRecord$1> {
  id: string;
  title: string;
  apiUrl: string;
  /**
   * Backend adapter that controls how the engine interacts with the API.
   * - `HydraAdapter` (default) — API Platform / JSON-LD + Hydra backends.
   * - `RestAdapter` — plain OpenAPI / REST backends with numeric or UUID ids.
   * - Provide a custom implementation for any other convention.
   */
  adapter?: BackendAdapter;
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
  /** Detail rows rendered in the main grid's expandable row panel. */
  gridDetail?: ResourceGridDetail;
  /** Detail rows rendered inside the create/edit form. */
  formDetail?: ResourceFormDetail;
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
  /** Fine-grained RBAC permission overrides. May be static or a callable that is
   *  evaluated at render time. */
  permissions?: ResourcePermissions;
  dialogWidth?: number;
  dialogHeight?: number;
  format?: 'json' | 'multipart';
  toolbar?: ResourceToolbar<T>;
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
//#region assets/crud/crud/defineResource.d.ts
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
declare function defineResource<T extends DataRecord$1>(apiUrl: string, overrides: Omit<ResourceConfig<T>, 'apiUrl'> & {
  id?: string;
}): ResourceConfig<T>;
/** Overload 3 — Full config object (legacy / full control — retrocompatible). */
declare function defineResource<T extends DataRecord$1 = DataRecord$1>(config: ResourceConfig<T>): ResourceConfig<T>;
//#endregion
//#region assets/crud/crud/usePermissions.d.ts
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
 *   2. inferred from `supportedOperations` (hydra:supportedOperation methods)
 *   3. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
 *
 * Memoized on `resource.id` — stable reference across re-renders.
 */
declare function usePermissions(resource: ResourceConfig, supportedOperations?: string[]): ResolvedPermissions;
//#endregion
//#region assets/crud/crud/useSelectionState.d.ts
interface SelectionState {
  selectedIds: (string | number)[];
  selectedCount: number;
  hasSelection: boolean;
  clearSelection: () => void;
}
declare function useSelectionState(identityField: string): SelectionState & {
  onSelectionChanged: (selectedRows: DataRecord$1[]) => void;
};
//#endregion
//#region assets/crud/crud/useColumnPreset.d.ts
interface ColumnPresetState {
  activePreset: string | null;
  setPreset: (key: string | null) => void;
  visibleColumns: string[] | null;
}
//#endregion
//#region assets/crud/crud/resolveCrudResource.d.ts
type ResolvedCrudResource<T extends DataRecord$1> = ResourceConfig<T> & {
  events: FormEventNames;
};
//#endregion
//#region assets/crud/crud/useCrudPage.d.ts
declare function useCrudPage<T extends DataRecord$1 = DataRecord$1>(resource: ResourceConfig<T>, externalFormRef?: React.RefObject<FormHandle | null>): {
  events: FormEventNames;
  resource: ResolvedCrudResource<T>;
  fields: Field[];
  formRef: React.RefObject<FormHandle | null>;
  permissions: ResolvedPermissions;
  selectionState: SelectionState & {
    onSelectionChanged: (rows: DataRecord$1[]) => void;
  };
  presetState: ColumnPresetState;
};
//#endregion
//#region assets/crud/crud/CrudPage.d.ts
interface CrudPageProps<T extends DataRecord$1 = DataRecord$1> {
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
  /**
   * Optional external form ref. When provided, CrudPage uses it instead of
   * creating an internal one — allows the parent to call setFieldValue /
   * getFieldValue on the active dialog form (e.g. for field observers).
   */
  formRef?: React$1.RefObject<FormHandle | null>;
  /**
   * Called whenever grid row selection changes, after internal selection state
   * is updated. Receives the full selected row objects.
   */
  onSelectionChanged?: (rows: DataRecord$1[]) => void;
  /** Disables the edit action on every grid row (passed through to DataGridView). */
  editDisabled?: boolean;
  /** Disables the delete action on every grid row (passed through to DataGridView). */
  deleteDisabled?: boolean;
  /** External ref forwarded to the DataGridView — allows the parent to call
   *  showLoading / hideLoading / refresh / getSelectedRow imperatively. */
  gridRef?: React$1.RefObject<GridHandle | null>;
}
/**
 * Public export — wraps CrudPageInner in its own DialogStoreProvider so that
 * existing pages do not need to add a provider themselves.
 */
declare const CrudPage: <T extends DataRecord$1 = DataRecord$1>({
  resource,
  onFormDataChange,
  initialRecordId,
  initialIsNew,
  computedValues,
  initialFilters,
  onFiltersChange,
  formRef,
  onSelectionChanged,
  editDisabled,
  deleteDisabled,
  gridRef
}: CrudPageProps<T>) => import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/crud/crud/resolveSmartCrudFields.d.ts
type FieldOverride = Partial<Field> & {
  key: string;
};
interface ResolveSmartCrudFieldsOptions<T extends SmartCrudRecord> {
  baselineFields?: readonly Field[];
  contract?: SmartCrudFieldContract<T>;
  legacyOverrides?: readonly FieldOverride[];
}
declare function mergeOverrides(inferred: Field[], overrides: readonly FieldOverride[]): Field[];
declare function resolveSmartCrudFields<T extends SmartCrudRecord>(options: ResolveSmartCrudFieldsOptions<T>): Field[];
//#endregion
//#region assets/crud/crud/SmartCrudPage.d.ts
interface SmartCrudPageProps<T extends DataRecord$1 = DataRecord$1> {
  resource: ResourceConfig<T>;
  /**
   * Legacy per-field overrides applied on top of auto-inferred Hydra fields.
   * Each entry must contain a `key` matching the field's `name`, plus any
   * `Field` properties to override (e.g. `{ key: 'name', label: 'Custom' }`).
   * Ignored when the canonical `resource.fieldContract` is present.
   * Has no effect when `resource.fields` is a non-empty manual array.
   */
  fieldOverrides?: FieldOverride[];
  /** External form ref forwarded to CrudPage — lets the parent call setFieldValue / getFieldValue. */
  formRef?: RefObject<FormHandle | null>;
  /** Called whenever grid row selection changes. Receives the full selected row objects. */
  onSelectionChanged?: (rows: DataRecord$1[]) => void;
  /** Disables the edit action on every grid row. */
  editDisabled?: boolean;
  /** Disables the delete action on every grid row. */
  deleteDisabled?: boolean;
  /** External ref forwarded to the DataGridView — allows the parent to call
   *  showLoading / hideLoading / refresh / getSelectedRow imperatively. */
  gridRef?: RefObject<GridHandle | null>;
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
declare function SmartCrudPage<T extends DataRecord$1 = DataRecord$1>({
  resource,
  fieldOverrides,
  formRef,
  onSelectionChanged,
  editDisabled,
  deleteDisabled,
  gridRef
}: SmartCrudPageProps<T>): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/crud/crud/SmartCrudRolesContext.d.ts
declare function SmartCrudRolesProvider({
  children,
  roles
}: React$1.PropsWithChildren<{
  roles?: string[];
}>): import("react/jsx-runtime").JSX.Element;
declare function useSmartCrudRoles(): string[];
//#endregion
//#region assets/crud/crud/resolveResourceDetails.d.ts
interface ResolvedResourceDetails {
  gridDetail?: ResourceGridDetail;
  formDetail?: ResourceFormDetail;
}
declare function resolveResourceDetails<T extends DataRecord$1>(resource: ResourceConfig<T>): ResolvedResourceDetails;
//#endregion
//#region assets/crud/crud/resolveResourceToolbar.d.ts
declare function resolveResourceToolbar<T extends DataRecord$1>(resource: ResourceConfig<T>, context: ResourceToolbarContext<T>): ResourceToolbarItems | undefined;
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
  'crudPage.schemaErrorTitle': string;
  'crudPage.schemaErrorMessage': string;
  'crudPage.schemaErrorDetails': string;
  'crudPage.schemaErrorRetry': string;
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
  'common.yes': string;
  'common.no': string;
  'validation.defaultError': string;
  'form.searchButton': string;
  'form.actionButton': string;
}
//#endregion
//#region assets/core/data/DataRecord.d.ts
type DataRecord$2 = Record<string, unknown>;
//#endregion
//#region assets/core/i18n/useCoreTranslation.d.ts
declare function useCoreTranslation(): {
  t: (key: keyof CoreTranslationKeys, options?: DataRecord$2) => string;
};
//#endregion
//#region assets/core/i18n/initCoreI18n.d.ts
declare function initCoreI18n(): void;
//#endregion
//#region assets/crud/field/BaseFieldBuilder.d.ts
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
  align(align: 'left' | 'center' | 'right'): this;
  sortable(sortable: boolean): this;
  filterable(filterable: boolean): this;
  hideable(hideable: boolean): this;
  validators(validators: ValidationRule[]): this;
  valueType(valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime'): this;
  filterValue(filterValue: string | number | boolean | Date | null | undefined): this;
  data(data: DataRecord$1[]): this;
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
  buttons(value: FieldButton[]): this;
  searchEnabled(value: boolean): this;
  searchExpr(value: string[]): this;
  helpText(value: string): this;
  contentRender(value: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined): this;
  visibleOnForm(value: boolean): this;
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
  maxLength(value: number): this;
  visibleWhen(fn: (formData: DataRecord$1) => boolean): this;
  disabledWhen(fn: (formData: DataRecord$1) => boolean): this;
  computed(fn: (formData: DataRecord$1) => unknown): this;
  defaultWhen(fn: (formData: DataRecord$1) => unknown): this;
  requiredWhen(fn: (formData: DataRecord$1) => boolean): this;
  clearWhenHidden(value?: boolean): this;
  dependsOn(fields: string[]): this;
  permissions(perms: {
    visible?: string[];
    editable?: string[];
  }): this;
  build(): Field;
}
//#endregion
//#region assets/crud/field/FieldBuilders.d.ts
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
//#region assets/crud/field/FieldBuilder.d.ts
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
  align(align: 'left' | 'center' | 'right'): FieldBuilder;
  sortable(sortable: boolean): FieldBuilder;
  filterable(filterable: boolean): FieldBuilder;
  hideable(hideable: boolean): FieldBuilder;
  validators(validators: ValidationRule[]): FieldBuilder;
  url(url: string): FieldBuilder;
  loadOptions(param: LoadOption[]): FieldBuilder;
  filters(filters: FilterRule[]): FieldBuilder;
  byKeyUrl(byKeyUrl: string): FieldBuilder;
  textField(textField: string): FieldBuilder;
  valueField(valueField: string): FieldBuilder;
  valueType(valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime'): FieldBuilder;
  selectedFilterOperation(operation: '<' | '<=' | '<>' | '=' | '>' | '>=' | 'between' | 'contains' | 'endswith' | 'notcontains' | 'startswith'): FieldBuilder;
  filterValue(filterValue: string | number | boolean | Date | null | undefined): FieldBuilder;
  data(data: DataRecord$1[]): FieldBuilder;
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
  buttons(value: FieldButton[]): FieldBuilder;
  searchEnabled(value: boolean): FieldBuilder;
  searchExpr(value: string[]): FieldBuilder;
  helpText(value: string): this;
  contentRender(value: string | ((...args: unknown[]) => React$1.ReactNode) | null | undefined): this;
  visibleOnForm(value: boolean): this;
  multiple(value: boolean): FieldBuilder;
  sendAsString(value: boolean): FieldBuilder;
  maxLength(value: number): FieldBuilder;
  visibleWhen(fn: (formData: DataRecord$1) => boolean): FieldBuilder;
  disabledWhen(fn: (formData: DataRecord$1) => boolean): FieldBuilder;
  computed(fn: (formData: DataRecord$1) => unknown): FieldBuilder;
  defaultWhen(fn: (formData: DataRecord$1) => unknown): FieldBuilder;
  requiredWhen(fn: (formData: DataRecord$1) => boolean): FieldBuilder;
  clearWhenHidden(value?: boolean): FieldBuilder;
  dependsOn(fields: string[]): FieldBuilder;
  permissions(value: NonNullable<Field['permissions']>): FieldBuilder;
  build(): Field;
}
//#endregion
//#region assets/crud/datagrid/DataGridEvents.d.ts
declare const DATA_GRID_EVENTS: {
  SELECTION_CHANGED: string;
  TOOLBAR_EVENT: string;
};
//#endregion
//#region assets/crud/datagrid/DataGridViewOptions.d.ts
interface DataGridSelectionChangedEvent {
  selectedRowsData?: DataRecord$1[];
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
  detailFields?: Field[] | ((parentRow: DataRecord$1) => Field[]);
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
  /** Backend adapter controlling entity key lookup and display. Defaults to HydraAdapter. */
  adapter?: BackendAdapter;
}
//#endregion
//#region assets/crud/datagrid/NativeDataGridView.d.ts
declare const NativeDataGridView: React$1.ForwardRefExoticComponent<DataGridViewOptions & React$1.RefAttributes<GridHandle>>;
//#endregion
//#region assets/crud/dialog/CrudDialogView.d.ts
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
//#region assets/crud/form/FormViewOptions.d.ts
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
  /** Backend adapter controlling entity serialization and URL building. Defaults to HydraAdapter. */
  adapter?: BackendAdapter;
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
}
//#endregion
//#region assets/crud/form/NativeFormView.d.ts
declare const NativeFormView: React$1.ForwardRefExoticComponent<FormViewOptions & React$1.RefAttributes<FormHandle>>;
//#endregion
//#region assets/crud/form/FormEvents.d.ts
declare const FORM_EVENTS: {
  FIELD_CHANGED: string;
  ROW_ADDED: string;
  ROW_REMOVED: string;
  ROW_UPDATED: string;
  LOADING: string;
  LOADING_DETAIL: string;
};
//#endregion
//#region assets/crud/data/ResourceStore.d.ts
type ResourceFilterDescriptor = unknown[];
type ResourceSortDescriptor = {
  selector?: string;
  desc?: boolean;
} | string;
interface ResourceFilterRule {
  field: string;
  operator: string;
  value: string | number | boolean;
}
type ResourceLoadOption = Record<string, unknown>;
type ResourceLoadOptions = DataRecord$1 & {
  filter?: ResourceFilterDescriptor;
  sort?: ResourceSortDescriptor[];
  appendData?: DataRecord$1[];
  prependData?: DataRecord$1[];
  searchExpr?: unknown;
  searchValue?: unknown;
  searchOperation?: unknown;
};
type ResourceLoadResult<TRecord extends DataRecord$1 = DataRecord$1> = GridData$1<TRecord>;
interface ResourceStoreOptions {
  url: string;
  idField: string;
  byKeyUrl?: string | null;
  defaultFilterRules?: ResourceFilterDescriptor[];
  defaultSortRules?: ResourceSortDescriptor[];
  options?: ResourceLoadOption[];
  iriMode?: boolean;
  httpClient?: CoreHttpClient;
}
interface ResourceStore<TRecord extends DataRecord$1 = DataRecord$1> {
  load(options: ResourceLoadOptions): Promise<ResourceLoadResult<TRecord>>;
  byKey?(key: unknown): Promise<TRecord | null>;
}
type ResourceStoreFactory = (options: ResourceStoreOptions) => ResourceStore;
interface ResourceStoreProviderProps {
  children: React$1.ReactNode;
  factory: ResourceStoreFactory;
}
declare function ResourceStoreProvider({
  children,
  factory
}: ResourceStoreProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useResourceStoreFactory(): ResourceStoreFactory;
//#endregion
//#region assets/crud/schema/ResourceSchema.d.ts
interface ResourceSchemaRequest {
  apiUrl: string;
}
interface ResourceSchemaResolution {
  fields: Field[];
  isLoading: boolean;
  error: Error | undefined;
  supportedOperations: string[];
}
interface ResourceSchemaResolver {
  useResourceSchema(request: ResourceSchemaRequest): ResourceSchemaResolution;
}
interface ResolveResourceFieldsOptions<T extends DataRecord$1 = DataRecord$1> {
  apiUrl: string;
  manualFields?: Field[];
  overrides?: FieldOverride[];
  fieldContract?: SmartCrudFieldContract<T>;
}
interface ResourceSchemaProviderProps {
  children: React$1.ReactNode;
  resolver: ResourceSchemaResolver;
}
declare function ResourceSchemaProvider({
  children,
  resolver
}: ResourceSchemaProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useResolvedResourceFields<T extends DataRecord$1 = DataRecord$1>({
  apiUrl,
  manualFields,
  overrides,
  fieldContract
}: ResolveResourceFieldsOptions<T>): ResourceSchemaResolution;
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
//#region assets/crud/adapter/HydraAdapter.d.ts
/**
 * Default backend adapter for API Platform / JSON-LD + Hydra backends.
 *
 * Conventions assumed:
 * - Records carry an `@id` IRI (e.g. `/api/users/5`) as their canonical identifier.
 * - Entity fields are serialized as IRI strings in POST/PATCH bodies.
 * - Collection responses follow the `hydra:member` / `hydra:totalItems` shape.
 * - `_iri` is a synthetic alias for `@id` used internally by the engine.
 */
declare const HydraAdapter: BackendAdapter;
//#endregion
//#region assets/crud/adapter/RestAdapter.d.ts
/**
 * Backend adapter for plain OpenAPI / REST backends.
 *
 * Conventions assumed:
 * - Records use a plain `id` field (numeric or string UUID) as their identifier.
 * - Entity fields are serialized as scalar IDs in POST/PATCH bodies.
 * - Collection responses are either a plain array or `{ items: [...], total: N }`.
 *   Also handles `{ data: [...], total: N }` (common in Laravel / many REST frameworks).
 */
declare const RestAdapter: BackendAdapter;
//#endregion
export { AppDialog, type AppDialogProps, type AuditEntry, type AuditTrailConfig, type BackendAdapter, type BulkAction, type ColumnPreset, ConfirmDialog, type ConfirmDialogProps, CrudPage, DATA_GRID_EVENTS, type DataGridSelectionChangedEvent, type DataGridSummaryItem, NativeDataGridView as DataGridView, type DataGridViewOptions, type DataRecord, CrudDialogView as DialogView, type EnumOption, FORM_EVENTS, type Field, FieldBuilder, type FieldDef, type FieldOverride, FieldType, type FilterRule, type FormHandle, type FormLayout, type FormOnChangeFn, type FormSection, type FormTab, NativeFormView as FormView, type FormatterFn, type GridCellContext, type GridData, type GridHandle, type GridOnChangeFn, HydraAdapter, type ItemFormatterFn, type LoadOption, type OnChangeFn, type ResolveResourceFieldsOptions, type ResolvedPermissions, type ResourceConfig, type ResourceFilterDescriptor, type ResourceFilterRule, type ResourceFormDetail, type ResourceGridDetail, type ResourceLoadOption, type ResourceLoadOptions, type ResourceLoadResult, type ResourcePermissions, type ResourceRouting, ResourceSchemaProvider, type ResourceSchemaProviderProps, type ResourceSchemaRequest, type ResourceSchemaResolution, type ResourceSchemaResolver, type ResourceSortDescriptor, type ResourceStore, type ResourceStoreFactory, type ResourceStoreOptions, ResourceStoreProvider, type ResourceStoreProviderProps, type ResourceToolbar, type ResourceToolbarContext, type ResourceToolbarItems, RestAdapter, type SelectionState, type SmartCrudFieldContract, type SmartCrudFieldOperation, type SmartCrudFieldPatch, type SmartCrudFieldResolutionStrategy, type SmartCrudFieldSourceMode, type SmartCrudHydraFieldContract, type SmartCrudHydraFieldDirective, type SmartCrudManualField, type SmartCrudManualFieldContract, type SmartCrudModelFieldKey, type SmartCrudOperation, SmartCrudPage, SmartCrudRolesProvider, type ValidationRule, checkboxField, createCrudEvents, createScopedEventBus, currencyField, dateField, datetimeField, defineFieldContract, defineFields, defineResource, entityField, enumField, identityField, imageField, initCoreI18n, mergeOverrides, noneField, numberField, passwordField, resolveResourceDetails, resolveResourceToolbar, resolveSmartCrudFields, selectField, switchField, textField, textareaField, useCoreTranslation, useCrudPage, usePermissions, useResolvedResourceFields, useResourceStoreFactory, useSelectionState, useSmartCrudRoles };