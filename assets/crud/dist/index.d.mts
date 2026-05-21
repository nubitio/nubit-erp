import React$1, { ReactNode, RefObject } from "react";
import { FieldOverride as FieldOverride$1 } from "@nubit/api-platform";
import { DataRecord, DataRecord as DataRecord$1, GridData } from "@nubit/core";
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
  data: DataRecord$1[];
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
  buttons: DataRecord$1[];
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
  editorOptions: DataRecord$1 | undefined;
  sendAsString: boolean;
  /** Column display order hint set via `x-crud.order` from Hydra metadata. */
  order?: number;
  /** Returns true when the field should be visible */
  visibleWhen?: (formData: DataRecord$1) => boolean;
  /** Returns true when the field should be disabled (read-only in form) */
  disabledWhen?: (formData: DataRecord$1) => boolean;
  /** Derived value — field is rendered as read-only with this computed value */
  computed?: (formData: DataRecord$1) => unknown;
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
interface SmartCrudResolvedFieldOperationState {
  visible: boolean;
  required: boolean;
  readonly: boolean;
  disabled: boolean;
}
type SmartCrudResolvedFieldOperationSemantics = Record<SmartCrudFieldOperation, SmartCrudResolvedFieldOperationState>;
type SmartCrudNormalizedFieldOperationSemantics = Record<SmartCrudFieldOperation, SmartCrudFieldOperationState>;
declare class SmartCrudFieldOperationSemanticsError extends Error {
  readonly issues: string[];
  constructor(message: string, issues?: string[]);
}
type SmartCrudFieldOperationDefaults = Pick<FieldDef<DataRecord$1>, 'visible' | 'hidden' | 'required' | 'readonly' | 'disabled'>;
declare function normalizeFieldOperationSemantics(behavior: SmartCrudFieldOperationBehavior | undefined, owner?: string): SmartCrudNormalizedFieldOperationSemantics;
/**
 * Validates field operation semantics and returns any issues found,
 * without throwing. Uses the same validation logic as
 * `normalizeFieldOperationSemantics` but collects issues instead of
 * throwing `SmartCrudFieldOperationSemanticsError`.
 */
declare function collectOperationSemanticsIssues(owner: string, behavior: SmartCrudFieldOperationBehavior | undefined): string[];
declare function resolveFieldOperationSemantics(field: Partial<SmartCrudFieldOperationDefaults>, behavior: SmartCrudFieldOperationBehavior | undefined, owner?: string): SmartCrudResolvedFieldOperationSemantics;
declare function applyFieldOperationSemantics<T extends DataRecord$1>(field: FieldDef<T>, operation: SmartCrudFieldOperation, behavior: SmartCrudFieldOperationBehavior | undefined, owner?: string, target?: 'grid' | 'form'): FieldDef<T>;
//#endregion
//#region assets/crud/crud/fieldValidation.d.ts
type SmartCrudRecord$1 = object;
type SmartCrudHydraDirectiveLike<T extends SmartCrudRecord$1> = {
  kind: 'override';
  key: Extract<keyof T, string>;
  patch: SmartCrudFieldPatchLike;
} | {
  kind: 'remove';
  key: Extract<keyof T, string>;
} | {
  kind: 'prepend';
  field: SmartCrudManualFieldLike;
} | {
  kind: 'append';
  field: SmartCrudManualFieldLike;
};
type SmartCrudFieldContractLike<T extends SmartCrudRecord$1> = {
  source: 'hydra';
  strategy: 'augment';
  directives: readonly SmartCrudHydraDirectiveLike<T>[];
} | {
  source: 'manual';
  strategy: 'replace';
  fields: readonly SmartCrudManualFieldLike[];
};
declare class SmartCrudFieldContractError extends Error {
  readonly issues: string[];
  constructor(message: string, issues?: string[]);
}
declare const FIELD_KEYS: readonly ["isIdentity", "type", "col", "name", "label", "width", "height", "minWidth", "minHeight", "align", "sortable", "filterable", "hideable", "validators", "mode", "url", "loadOptions", "filters", "byKeyUrl", "textField", "valueField", "valueType", "format", "selectedFilterOperation", "filterValue", "data", "formatter", "itemFormatter", "visible", "defaultValue", "autoSelectIfSingle", "onChange", "onSelect", "onClick", "onKeyPress", "readonly", "disabled", "hidden", "required", "precision", "accept", "buttons", "hidingPriority", "searchEnabled", "searchExpr", "fixed", "fixedPosition", "helpText", "contentRender", "visibleOnForm", "maxLength", "multiple", "editorOptions", "sendAsString", "order", "visibleWhen", "disabledWhen", "computed", "dependsOn", "permissions"];
type SmartCrudFieldShape = Partial<Record<(typeof FIELD_KEYS)[number], unknown>>;
type SmartCrudFieldPatchLike = SmartCrudFieldShape & {
  operation?: SmartCrudFieldOperationBehavior;
};
type SmartCrudManualFieldLike = SmartCrudFieldShape & {
  name: string;
  operation?: SmartCrudFieldOperationBehavior;
};
declare function validateFieldContract<T extends SmartCrudRecord$1>(contract: SmartCrudFieldContractLike<T>): SmartCrudFieldContractLike<T>;
declare function validateHydraFieldResolutionInput<T extends SmartCrudRecord$1>(baselineFields: readonly Field[], contract: Extract<SmartCrudFieldContractLike<T>, {
  source: 'hydra';
}>): void;
declare function validateResolvedFieldNames(fields: readonly Field[], owner: string): void;
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
interface ResourceConfig<T extends DataRecord$1 = DataRecord$1> {
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
declare function useColumnPreset(resource: ResourceConfig): ColumnPresetState;
//#endregion
//#region assets/crud/crud/resolveCrudResource.d.ts
type ResolvedCrudResource<T extends DataRecord$1> = ResourceConfig<T> & {
  events: FormEventNames;
};
//#endregion
//#region assets/crud/crud/useCrudPage.d.ts
declare function useCrudPage<T extends DataRecord$1 = DataRecord$1>(resource: ResourceConfig<T>): {
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
  onFiltersChange
}: CrudPageProps<T>) => import("react/jsx-runtime").JSX.Element;
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
declare function SmartCrudPage<T extends DataRecord$1 = DataRecord$1>({
  resource,
  fieldOverrides
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
//#region assets/crud/crud/AuditTrailPanel.d.ts
interface AuditTrailPanelProps {
  url: string | null;
  renderEntry?: (entry: AuditEntry) => ReactNode;
  visible: boolean;
  onClose: () => void;
}
declare function AuditTrailPanel({
  url,
  renderEntry,
  visible,
  onClose
}: AuditTrailPanelProps): React$1.JSX.Element | null;
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
//#region assets/crud/crud/dialogStore.d.ts
type DialogMode = 'add' | 'edit' | 'view';
interface DialogState {
  isOpen: boolean;
  mode: DialogMode;
  rowData: DataRecord$1 | null;
}
interface DialogActions {
  openDialog: (mode: DialogMode, rowData?: DataRecord$1 | null) => void;
  closeDialog: () => void;
}
type DialogSlice = DialogState & DialogActions;
//#endregion
//#region assets/crud/crud/DialogStoreProvider.d.ts
declare function DialogStoreProvider({
  children
}: {
  children: ReactNode;
}): React$1.JSX.Element;
//#endregion
//#region assets/crud/crud/useCrudDialogStore.d.ts
declare function useCrudDialogStore(resourceId: string): DialogSlice;
import * as import__nubit_api_platform from "@nubit/api-platform";
//#endregion
//#region assets/crud/crud/rules/useConditionalRules.d.ts
interface FieldState {
  name: string;
  visible: boolean;
  disabled: boolean;
  computedValue?: unknown;
}
/**
 * Given the current Field definitions and live form data,
 * returns the reactive state (visible/disabled/computedValue) for each field.
 *
 * Pure computation — re-runs whenever formData changes.
 *
 * When `formData` is `null`, SmartCrud has not hydrated the form snapshot yet
 * (typically while opening an edit dialog), so rules are skipped and defaults
 * are returned. Empty objects `{}` are treated as valid create-form state.
 */
declare function useConditionalRules(fields: Field[], formData: FormDataRecord | null): FieldState[];
//#endregion
//#region assets/crud/crud/rules/useFieldPermissions.d.ts
/**
 * Filters and marks fields based on user roles.
 * IMPORTANT: This is UI-only. Backend is the real authorization source.
 *
 * - If field.permissions.visible is set and userRoles has no intersection → filter out the field
 * - If field.permissions.editable is set and userRoles has no intersection → mark field as disabled
 *
  * Identity fields (isIdentity === true) are always kept regardless of permissions
  * so grids and forms can resolve row keys consistently.
 */
declare function useFieldPermissions(fields: Field[], userRoles: string[]): Field[];
//#endregion
//#region assets/crud/crud/rules/useDependsOn.d.ts
/**
 * For each field that declares `dependsOn`, invalidate its entity query
 * (keyed by the field's `url`) whenever one of the watched form values changes.
 *
 * Only fires when a dependency value actually changes (compared per-value).
 * Skips the initial mount run to prevent spurious refetches when a dialog opens
 * with already-cached data (BUG-009).
 *
 * Also skips evaluation while `formData` is `null`, which SmartCrudPage uses
 * for edit dialogs that have not hydrated their initial snapshot yet.
 */
declare function useDependsOn(fields: Field[], formData: FormDataRecord | null): void;
//#endregion
//#region assets/crud/crud/routing/useRouting.d.ts
interface RoutingState {
  /** id of record to open on mount, or null */
  initialRecordId: string | null;
  /** true when URL signals a new-record dialog */
  initialIsNew: boolean;
  /** current filter values read from URL query params */
  initialFilters: Record<string, string>;
  /** call this when filters change to sync them to URL */
  syncFilters: (filters: Record<string, string>) => void;
}
/**
 * Reads URL params and exposes helpers for SmartCrudPage to:
 * - Open the edit dialog for the record whose id is in the URL
 * - Open the add dialog when the URL contains the sentinel value "new"
 * - Sync grid filters to/from URL query params
 *
 * If `routing` is undefined, returns neutral defaults so the feature
 * is completely opt-in (no side-effects when routing is not configured).
 */
declare function useRouting(routing: ResourceRouting | undefined): RoutingState;
declare namespace index_d_exports {
  export { AuditEntry, AuditTrailConfig, AuditTrailPanel, AuditTrailPanelProps, BulkAction, ColumnPreset, ColumnPresetState, CrudPage, DialogActions, DialogMode, DialogSlice, DialogState, DialogStoreProvider, FieldDef, FieldOverride, FieldState, FieldsFactory, FormLayout, FormSection, FormTab, ResolvedPermissions, ResourceConfig, ResourcePermissions, ResourceRouting, RoutingState, ScopedFormEventNames, SelectionState, SmartCrudFieldContract, SmartCrudFieldContractError, SmartCrudFieldOperation, SmartCrudFieldOperationBehavior, SmartCrudFieldOperationFlag, SmartCrudFieldOperationSemanticsError, SmartCrudFieldOperationState, SmartCrudFieldPatch, SmartCrudFieldResolutionStrategy, SmartCrudFieldSourceMode, SmartCrudHydraFieldContract, SmartCrudHydraFieldDirective, SmartCrudManualField, SmartCrudManualFieldContract, SmartCrudModelFieldKey, SmartCrudNormalizedFieldOperationSemantics, SmartCrudOperation, SmartCrudPage, SmartCrudRecord, SmartCrudResolvedFieldOperationSemantics, SmartCrudResolvedFieldOperationState, SmartCrudRolesProvider, applyFieldOperationSemantics, collectOperationSemanticsIssues, createScopedEventBus, defineFieldContract, defineFields, defineResource, initCoreI18n, mergeOverrides, normalizeFieldOperationSemantics, resolveFieldOperationSemantics, resolveSmartCrudFields, useColumnPreset, useConditionalRules, useCoreTranslation, useCrudDialogStore, useCrudPage, useDependsOn, useFieldPermissions, usePermissions, useRouting, useSelectionState, useSmartCrudRoles, validateFieldContract, validateHydraFieldResolutionInput, validateResolvedFieldNames };
}
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
  minHeight(minHeight: number): this;
  align(align: 'left' | 'center' | 'right'): this;
  sortable(sortable: boolean): this;
  filterable(filterable: boolean): this;
  hideable(hideable: boolean): this;
  validators(validators: ValidationRule[]): this;
  mode(mode: 'remote' | 'local' | 'none'): this;
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
  buttons(value: DataRecord$1[]): this;
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
  editorOptions(param: DataRecord$1): this;
  maxLength(value: number): this;
  visibleWhen(fn: (formData: DataRecord$1) => boolean): this;
  disabledWhen(fn: (formData: DataRecord$1) => boolean): this;
  computed(fn: (formData: DataRecord$1) => unknown): this;
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
  buttons(value: DataRecord$1[]): FieldBuilder;
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
  editorOptions(param: DataRecord$1): this;
  sendAsString(value: boolean): FieldBuilder;
  maxLength(value: number): FieldBuilder;
  build(): Field;
}
//#endregion
//#region assets/crud/datagrid/DataGridEvents.d.ts
declare const DATA_GRID_EVENTS: {
  SELECTION_CHANGED: string;
  TOOLBAR_EVENT: string;
};
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
export { AppDialog, type AppDialogProps, type AuditEntry, type AuditTrailConfig, type BulkAction, type ColumnPreset, ConfirmDialog, type ConfirmDialogProps, CrudPage, DATA_GRID_EVENTS, type DataGridSelectionChangedEvent, type DataGridSummaryItem, NativeDataGridView as DataGridView, type DataGridViewOptions, type DataRecord, CrudDialogView as DialogView, type EnumOption, FORM_EVENTS, type Field, FieldBuilder, type FieldDef, type FieldOverride, FieldType, type FilterRule, type FormHandle, type FormLayout, type FormOnChangeFn, type FormSection, type FormTab, NativeFormView as FormView, type FormatterFn, type GridCellContext, type GridData, type GridHandle, type GridOnChangeFn, type ItemFormatterFn, type LoadOption, type OnChangeFn, type ResolvedPermissions, type ResourceConfig, type ResourcePermissions, type ResourceRouting, type SelectionState, type SmartCrudFieldContract, type SmartCrudFieldOperation, type SmartCrudFieldOperationBehavior, type SmartCrudFieldOperationFlag, type SmartCrudFieldOperationState, type SmartCrudFieldPatch, type SmartCrudFieldResolutionStrategy, type SmartCrudFieldSourceMode, type SmartCrudHydraFieldContract, type SmartCrudHydraFieldDirective, type SmartCrudManualField, type SmartCrudManualFieldContract, type SmartCrudModelFieldKey, type SmartCrudOperation, SmartCrudPage, type SmartCrudRecord, SmartCrudRolesProvider, type ValidationRule, checkboxField, createCrudEvents, createScopedEventBus, currencyField, dateField, datetimeField, defineFieldContract, defineFields, defineResource, entityField, enumField, identityField, imageField, initCoreI18n, mergeOverrides, noneField, numberField, passwordField, resolveSmartCrudFields, selectField, switchField, textField, textareaField, useCoreTranslation, useCrudPage, usePermissions, useSelectionState, useSmartCrudRoles };