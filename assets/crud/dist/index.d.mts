import React, { ReactNode, RefObject } from "react";
import { AppDropdownOption } from "@nubit/ui";
import { CoreHttpClient, DataGridEventNames, DataRecord, DataRecord as DataRecord$1, DialogEventNames, FormEventNames, GridData, GridData as GridData$1, createCrudEvents } from "@nubit/core";

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
    comparisonTarget: () => unknown;
    comparisonType?: '==' | '!=' | '>' | '<' | '>=' | '<=';
    message?: string;
  };
};
type CustomRule = {
  type: 'custom';
  options: {
    validationCallback: (value: unknown) => boolean;
    message?: string;
  };
};
type AsyncRule = {
  type: 'async';
  options: {
    validationCallback: (value: unknown) => Promise<boolean>;
    message?: string;
  };
};
type ValidationRule = RequiredRule | EmailRule | NumericRule | PatternRule | StringLengthRule | RangeRule | CompareRule | CustomRule | AsyncRule;
//#endregion
//#region assets/crud/field/FieldCallbacks.d.ts
interface GridCellContext<TData extends DataRecord$1 = DataRecord$1, TValue = unknown> {
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
 * Pass a TData type argument to get typed access to cell.data:
 *   FormatterFn<InvoiceRecord>
 *   (cell: GridCellContext<InvoiceRecord>) => cell.data.customer?.ruc
 */
type FormatterFn<TData extends DataRecord$1 = DataRecord$1> = (cell: GridCellContext<TData>) => React.ReactNode;
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
type ItemFormatterFn = (item: unknown) => React.ReactNode;
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
  contentRender: string | ((...args: unknown[]) => React.ReactNode) | null | undefined;
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
  save: () => void;
  deleteRow: (row: FormDataRecord) => void;
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
declare const FIELD_KEYS: readonly ["isIdentity", "type", "col", "name", "label", "width", "height", "minWidth", "align", "sortable", "filterable", "hideable", "validators", "url", "loadOptions", "filters", "byKeyUrl", "textField", "valueField", "valueType", "format", "selectedFilterOperation", "filterValue", "data", "formatter", "itemFormatter", "visible", "defaultValue", "autoSelectIfSingle", "onChange", "onSelect", "onClick", "readonly", "disabled", "hidden", "required", "precision", "accept", "buttons", "searchEnabled", "searchExpr", "helpText", "contentRender", "visibleOnForm", "maxLength", "multiple", "sendAsString", "order", "visibleWhen", "disabledWhen", "computed", "defaultWhen", "requiredWhen", "clearWhenHidden", "dependsOn", "permissions"];
type SmartCrudFieldShape = Partial<Record<(typeof FIELD_KEYS)[number], unknown>>;
type SmartCrudFieldPatchLike = SmartCrudFieldShape & {
  operation?: SmartCrudFieldOperationBehavior;
};
type SmartCrudManualFieldLike = SmartCrudFieldShape & {
  name: string;
  operation?: SmartCrudFieldOperationBehavior;
};
declare function validateFieldContract<T extends SmartCrudRecord$1>(contract: SmartCrudFieldContractLike<T>): SmartCrudFieldContractLike<T>;
//#endregion
//#region assets/crud/crud/fieldContract.d.ts
type SmartCrudRecord = DataRecord$1;
type SmartCrudModelFieldKey<T extends SmartCrudRecord> = Extract<keyof T, string>;
type SmartCrudFieldRecord<T extends SmartCrudRecord> = T & DataRecord$1;
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
  canView?: boolean | (() => boolean);
  canDelete?: boolean | (() => boolean);
  canExport?: boolean | (() => boolean);
  canBulkDelete?: boolean | (() => boolean);
}
type ResourceToolbarActionVariant = string;
interface ResourceToolbarAction {
  key?: string;
  text: string;
  icon?: string;
  hint?: string;
  type?: ResourceToolbarActionVariant;
  group?: string;
  groupLabel?: string;
  permission?: string;
  visible?: boolean;
  disabled?: boolean;
  onClick?: () => void;
}
type ResourceRowActions<T extends DataRecord$1 = DataRecord$1> = ResourceToolbarAction[] | ((row: T) => ResourceToolbarAction[]);
interface ResourceToolbarItems {
  primary?: ResourceToolbarAction[];
  selection?: ResourceToolbarAction[];
  utility?: ResourceToolbarAction[];
  showRefresh?: boolean;
}
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
/**
 * Where the create/edit form is rendered.
 *
 * - `'dialog'` (default) — modal centred over the grid. Best for short forms.
 * - `'drawer'` — slide-in panel from the right (full-screen sheet on mobile).
 *   Grid stays visible — user can reference other rows while editing.
 * - `'page'`   — full route. The grid is replaced by the form; Save/Cancel
 *   navigate back to the list URL. Requires the route to accept `:id?`.
 *   Use the `crudRoute()` helper to wire `/<resource>` and `/<resource>/:id?`
 *   in one call.
 */
type CrudViewMode = 'dialog' | 'drawer' | 'page';
interface CrudViewModeConfig {
  mode: CrudViewMode;
  /** Drawer width. Default: `min(720px, 60vw)`. */
  drawerWidth?: number | string;
  /** Side the drawer slides in from. Default: `'right'`. */
  drawerSide?: 'right' | 'left';
}
/** Contextual empty state shown when the grid has no rows. */
interface ResourceEmptyState {
  title?: string;
  description?: string;
  /** Phosphor icon name without the "ph-" prefix. */
  icon?: string;
  variant?: 'default' | 'danger' | 'warning' | 'success';
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
   * Manual field definitions for this resource.
   * Omit (or pass an empty array) to let SmartCrudPage auto-infer fields from
   * the Hydra/OpenAPI schema. Prefer `fieldContract` for augmenting inferred fields.
   */
  fields?: Field[] | FieldDef<T>[];
  /**
   * Form-only field definitions. When set, the grid uses `fields` and the
   * create/edit form uses `formFields` — avoids re-fetching the grid on every
   * reactive form rule evaluation (e.g. visibleWhen while typing).
   */
  formFields?: Field[];
  /**
   * Canonical production field contract for SmartCrud.
   * SmartCrud runtime treats this as authoritative over legacy
   * `fields` + `fieldOverrides` combinations whenever it is present.
   */
  fieldContract?: SmartCrudFieldContract<T>;
  /**
   * @deprecated Prefer callback/state coordination. This remains only as a legacy
   * escape hatch for code that still listens to CRUD event names directly.
   */
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
  /**
   * Where the create/edit form is rendered. Defaults to `'dialog'`.
   *
   * Examples:
   *   viewMode: 'drawer'
   *   viewMode: { mode: 'drawer', drawerWidth: 880 }
   *   viewMode: 'page'
   */
  viewMode?: CrudViewMode | CrudViewModeConfig;
  format?: 'json' | 'multipart';
  toolbar?: ResourceToolbar<T>;
  rowActions?: ResourceRowActions<T>;
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
  /** Custom empty state when the grid has no rows. Falls back to the generic no-records message. */
  emptyState?: ResourceEmptyState;
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
/** Zero config — `id` and `title` derived from the URL. */
declare function defineResource(apiUrl: string): ResourceConfig;
/**
 * URL + partial overrides (canonical form).
 * Pass `mercure: false` to disable the real-time Mercure subscription.
 */
declare function defineResource<T extends DataRecord$1>(apiUrl: string, overrides: Partial<Omit<ResourceConfig<T>, 'apiUrl'>> & {
  id?: string;
}): ResourceConfig<T>;
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
  formRef?: React.RefObject<FormHandle | null>;
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
  gridRef?: React.RefObject<GridHandle | null>;
  onOperationChange?: (operation: SmartCrudOperation | null) => void;
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
  gridRef,
  onOperationChange
}: CrudPageProps<T>) => React.JSX.Element;
//#endregion
//#region assets/crud/crud/resolveSmartCrudFields.d.ts
type FieldOverride = Partial<Field> & {
  key: string;
};
//#endregion
//#region assets/crud/crud/SmartCrudPage.d.ts
interface SmartCrudPageProps<T extends DataRecord$1 = DataRecord$1> {
  resource: ResourceConfig<T>;
  /**
   * @deprecated Use `resource.fieldContract` (defineFieldContract) instead.
   * Per-field overrides applied on top of auto-inferred Hydra fields.
   * Ignored when `resource.fieldContract` is present.
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
}: SmartCrudPageProps<T>): React.JSX.Element;
//#endregion
//#region assets/crud/crud/SmartCrudRolesContext.d.ts
declare function SmartCrudRolesProvider({
  children,
  roles
}: React.PropsWithChildren<{
  roles?: string[];
}>): React.JSX.Element;
declare function useSmartCrudRoles(): string[];
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
}: AuditTrailPanelProps): React.JSX.Element | null;
//#endregion
//#region assets/crud/field/BaseFieldBuilder.d.ts
declare class BaseFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> {
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
  formatter(formatter: (cell: GridCellContext<TRecord>) => React.ReactNode): this;
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
  contentRender(value: string | ((...args: unknown[]) => React.ReactNode) | null | undefined): this;
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
  visibleWhen(fn: (formData: TRecord) => boolean): this;
  disabledWhen(fn: (formData: TRecord) => boolean): this;
  computed(fn: (formData: TRecord) => unknown): this;
  defaultWhen(fn: (formData: TRecord) => unknown): this;
  requiredWhen(fn: (formData: TRecord) => boolean): this;
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
declare class IdentityFieldBuilder extends BaseFieldBuilder<DataRecord$1> {
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
declare class EntityFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor(url: string, valueField: string, textField: string);
}
declare function entityField<TRecord extends DataRecord$1 = DataRecord$1>(url: string, valueField: string, textField: string): EntityFieldBuilder<TRecord>;
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
declare class CurrencyFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function currencyField<TRecord extends DataRecord$1 = DataRecord$1>(): CurrencyFieldBuilder<TRecord>;
/**
 * Factory for generic file upload fields (FILE type).
 *
 * Uploads immediately to apiBaseUrl+'media' and submits the returned Media IRI.
 * Use `.accept()` to restrict mime types or extensions.
 *
 * Usage:
 *   fileField('/api/')
 *     .name('attachment')
 *     .label('Adjunto')
 *     .accept('application/pdf')
 *     .build()
 */
declare class FileFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor(apiBaseUrl: string);
}
declare function fileField<TRecord extends DataRecord$1 = DataRecord$1>(apiBaseUrl: string): FileFieldBuilder<TRecord>;
/**
 * Preset for image uploads — shorthand over fileField with image defaults.
 *
 * Defaults: url=apiBaseUrl+'media', accept='image/*', align='center'
 */
declare class ImageFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends FileFieldBuilder<TRecord> {
  constructor(apiBaseUrl: string);
}
declare function imageField<TRecord extends DataRecord$1 = DataRecord$1>(apiBaseUrl: string): ImageFieldBuilder<TRecord>;
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
declare class DateFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function dateField<TRecord extends DataRecord$1 = DataRecord$1>(): DateFieldBuilder<TRecord>;
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
declare class DateTimeFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function datetimeField<TRecord extends DataRecord$1 = DataRecord$1>(): DateTimeFieldBuilder<TRecord>;
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
declare class NumberFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function numberField<TRecord extends DataRecord$1 = DataRecord$1>(): NumberFieldBuilder<TRecord>;
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
declare class EnumFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor(options: EnumOption[]);
}
declare function enumField<TRecord extends DataRecord$1 = DataRecord$1>(options: EnumOption[]): EnumFieldBuilder<TRecord>;
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
declare class TextFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function textField<TRecord extends DataRecord$1 = DataRecord$1>(): TextFieldBuilder<TRecord>;
/**
 * Builder for TEXTAREA (multi-line text) fields.
 *
 * Usage:
 *   textareaField()
 *     .name('note')
 *     .label('Nota')
 *     .build()
 */
declare class TextareaFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function textareaField<TRecord extends DataRecord$1 = DataRecord$1>(): TextareaFieldBuilder<TRecord>;
/**
 * Builder for NONE (display-only, no editor) fields.
 *
 * Usage:
 *   noneField()
 *     .name('status')
 *     .label('Status')
 *     .formatter(cell => statusFormatter(cell.value))
 *     .build()
 */
declare class NoneFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function noneField<TRecord extends DataRecord$1 = DataRecord$1>(): NoneFieldBuilder<TRecord>;
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
declare class SwitchFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function switchField<TRecord extends DataRecord$1 = DataRecord$1>(): SwitchFieldBuilder<TRecord>;
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
declare class SelectFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function selectField<TRecord extends DataRecord$1 = DataRecord$1>(): SelectFieldBuilder<TRecord>;
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
declare class CheckboxFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function checkboxField<TRecord extends DataRecord$1 = DataRecord$1>(): CheckboxFieldBuilder<TRecord>;
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
declare class PasswordFieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> extends BaseFieldBuilder<TRecord> {
  constructor();
}
declare function passwordField<TRecord extends DataRecord$1 = DataRecord$1>(): PasswordFieldBuilder<TRecord>;
//#endregion
//#region assets/crud/field/FieldBuilder.d.ts
declare class FieldBuilder<TRecord extends DataRecord$1 = DataRecord$1> {
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
  formatter(formatter: (cell: GridCellContext<TRecord>) => React.ReactNode): FieldBuilder<TRecord>;
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
  contentRender(value: string | ((...args: unknown[]) => React.ReactNode) | null | undefined): this;
  visibleOnForm(value: boolean): this;
  multiple(value: boolean): FieldBuilder;
  sendAsString(value: boolean): FieldBuilder;
  maxLength(value: number): FieldBuilder;
  visibleWhen(fn: (formData: TRecord) => boolean): FieldBuilder<TRecord>;
  disabledWhen(fn: (formData: TRecord) => boolean): FieldBuilder<TRecord>;
  computed(fn: (formData: TRecord) => unknown): FieldBuilder<TRecord>;
  defaultWhen(fn: (formData: TRecord) => unknown): FieldBuilder<TRecord>;
  requiredWhen(fn: (formData: TRecord) => boolean): FieldBuilder<TRecord>;
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
//#region assets/crud/summary/SummaryTypes.d.ts
type SummaryType = 'sum' | 'count' | 'avg' | 'min' | 'max' | 'custom';
type SummaryFormat = 'currency' | 'fixedPoint' | 'decimal' | 'percent' | Intl.NumberFormatOptions | ((value: unknown, item: SummaryItem) => string);
interface SummaryCalculateContext {
  rows: DataRecord$1[];
  column?: string;
  item: SummaryItem;
}
interface SummaryTextContext {
  value: unknown;
  valueText: string;
  item: SummaryItem;
}
interface SummaryItem {
  /** Field/column name used to read values and align table-based summaries. */
  column?: string;
  /** Optional label shown by table footer renderers before the computed value. */
  label?: string;
  /** Built-in aggregation or a custom calculator for reusable domain summaries. */
  summaryType?: SummaryType | ((context: SummaryCalculateContext) => unknown);
  /** Template applied after value formatting, e.g. "Total: {0}". */
  displayFormat?: string;
  /** Number format preset, Intl.NumberFormatOptions, or custom formatter. */
  valueFormat?: SummaryFormat;
  /** Final text customization hook. */
  customizeText?: (cellInfo: SummaryTextContext) => string;
  /** Decimal digits used by number presets. Defaults to 2 for currency/fixedPoint. */
  precision?: number;
  /** ISO currency used by the currency preset. Defaults to PEN. */
  currency?: string;
  /** Intl currency display for the currency preset. Defaults to narrowSymbol. */
  currencyDisplay?: Intl.NumberFormatOptions['currencyDisplay'];
  /** Text alignment override for renderers that are not tied to a Field. */
  align?: 'left' | 'center' | 'right';
}
interface DetailSummaryOptions {
  items: SummaryItem[];
  /** Keeps the summary visible at the bottom of the scrollable detail table. */
  sticky?: boolean;
  /** Allows temporarily disabling summaries without changing the item list. */
  visible?: boolean;
}
//#endregion
//#region assets/crud/summary/SummaryUtils.d.ts
declare function computeSummaryValue(rows: DataRecord$1[], item: SummaryItem): unknown;
declare function formatSummaryValue(value: unknown, item: SummaryItem): string;
declare function resolveSummaryText(rows: DataRecord$1[], item: SummaryItem): string;
//#endregion
//#region assets/crud/datagrid/DataGridViewOptions.d.ts
interface DataGridSelectionChangedEvent {
  selectedRowsData?: DataRecord$1[];
}
type DataGridSummaryItem = SummaryItem;
interface DataGridViewOptions {
  id: string;
  title: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[] | ((parentRow: DataRecord$1) => Field[]);
  toolbar?: ResourceToolbarItems;
  rowActions?: ResourceRowActions;
  /**
   * @deprecated Prefer onAdd/onEdit/onDelete callbacks. Events remain as a
   * legacy fallback for direct DataGridView consumers.
   */
  events?: DataGridEventNames;
  allowAdd?: boolean;
  allowEdit?: boolean;
  allowView?: boolean;
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
  onAdd?: () => void;
  onEdit?: (row: DataRecord$1) => void;
  onView?: (row: DataRecord$1) => void;
  onDelete?: (row: DataRecord$1) => void;
  onSelectionChanged?: (event: DataGridSelectionChangedEvent) => void;
  onRowPrepared?: (event: unknown) => void;
  onContentReady?: () => void;
  onFilterChange?: (filters: Record<string, string>) => void;
  filterRow?: boolean;
  headerFilter?: boolean;
  manualLoad?: boolean;
  beforeToolbar?: () => ReactNode;
  errorMessage?: string | null;
  onDismissError?: () => void;
  editMode?: 'popup' | 'row' | 'cell' | 'batch';
  visibleColumns?: string[] | null;
  /** Custom empty state when the grid has no rows. */
  emptyState?: ResourceEmptyState;
  /** Backend adapter controlling entity key lookup and display. Defaults to HydraAdapter. */
  adapter?: BackendAdapter;
}
//#endregion
//#region assets/crud/datagrid/NativeDataGridView.d.ts
declare const NativeDataGridView: React.ForwardRefExoticComponent<DataGridViewOptions & React.RefAttributes<GridHandle>>;
//#endregion
//#region assets/crud/dialog/CrudDialogView.d.ts
interface CrudDialogViewOptions {
  title?: string;
  width?: number;
  height?: number;
  maxHeight?: number;
  visible?: boolean;
  /**
   * @deprecated Prefer controlled open state plus onSave/onCancel callbacks.
   * Events remain as a legacy fallback for direct DialogView consumers.
   */
  events?: DialogEventNames;
  children?: React.ReactNode;
  positiveText?: string;
  negativeText?: string;
  footerVisible?: boolean;
  fitContent?: boolean;
  isOpen?: boolean;
  onClose?: () => void;
  onCancel?: () => void;
  onSave?: () => void;
  /** When false, only the cancel/close button is shown in the footer. */
  saveVisible?: boolean;
}
interface CrudDialogViewEvents {
  open: (title?: string) => void;
  close: () => void;
}
declare const CrudDialogView: React.ForwardRefExoticComponent<CrudDialogViewOptions & React.RefAttributes<CrudDialogViewEvents>>;
//#endregion
//#region assets/crud/view/CrudDrawerView.d.ts
interface CrudDrawerViewOptions {
  title?: string;
  drawerWidth?: number | string;
  drawerSide?: 'right' | 'left';
  visible?: boolean;
  /**
   * @deprecated Prefer controlled open state plus onSave/onCancel callbacks.
   * Events remain as a legacy fallback for direct DrawerView consumers.
   */
  events?: DialogEventNames;
  children?: React.ReactNode;
  positiveText?: string;
  negativeText?: string;
  footerVisible?: boolean;
  isOpen?: boolean;
  onClose?: () => void;
  onCancel?: () => void;
  onSave?: () => void;
}
interface CrudDrawerViewEvents {
  open: (title?: string) => void;
  close: () => void;
}
/**
 * Event-driven drawer shell for legacy pages (SalePage, PriceListPage, etc.).
 * Mirrors `DialogView` API but slides in from the side so the grid stays visible.
 */
declare const CrudDrawerView: React.ForwardRefExoticComponent<CrudDrawerViewOptions & React.RefAttributes<CrudDrawerViewEvents>>;
//#endregion
//#region assets/crud/form/FormViewOptions.d.ts
interface FormViewOptions {
  className?: string;
  url: string;
  detailUrl?: string;
  fields: Field[];
  detailFields?: Field[];
  /** Optional summary footer for master-detail line tables. */
  detailSummary?: DetailSummaryOptions;
  detailPropertyName?: string;
  allowAdding?: boolean;
  allowUpdating?: boolean;
  allowDeleting?: boolean;
  /**
   * @deprecated Prefer operation/rowData plus the imperative FormHandle save/deleteRow methods.
   * Events remain as a legacy fallback for direct FormView consumers.
   */
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
  /** Callback/state-driven operation. When set, replaces ADD/EDIT event bootstrapping. */
  operation?: 'add' | 'edit' | null;
  /** Row payload used when operation changes. */
  rowData?: FormDataRecord | null;
  /** Increment to force re-applying the current operation and row payload. */
  operationVersion?: number;
}
//#endregion
//#region assets/crud/form/NativeFormView.d.ts
declare const NativeFormView: React.ForwardRefExoticComponent<FormViewOptions & React.RefAttributes<FormHandle>>;
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
//#region assets/crud/crud/dialogStore.d.ts
type DialogMode = 'add' | 'edit' | 'view';
//#endregion
//#region assets/crud/view/CrudFormShellProps.d.ts
/**
 * Common API consumed by every CRUD form shell — Dialog, Drawer, Page.
 *
 * The shell is purely a presentational container around the `FormView` passed
 * in `children`. The orchestrator (`CrudPage`) owns the form lifecycle and
 * tells the shell only when to open/close.
 */
interface CrudFormShellProps {
  /** Whether the form is currently being shown. */
  isOpen: boolean;
  /** add | edit | view — drives the title and (for some shells) intent UI. */
  mode: DialogMode;
  /** Localised title (already mapped from mode by the orchestrator). */
  title: string;
  /** Called when the user dismisses the shell (Escape, scrim, back nav). */
  onClose: () => void;
  /** Called when the user clicks the Cancel button. */
  onCancel: () => void;
  /** Called when the user clicks the Save button. */
  onSave: () => void;
  /** Dialog-only sizing. Drawer/Page shells ignore these. */
  width?: number;
  height?: number;
  /** Drawer-only sizing. */
  drawerWidth?: number | string;
  drawerSide?: 'right' | 'left';
  /**
   * Legacy event bus — passed through to keep the existing event-driven
   * close/loading behaviour working until consumers migrate to callbacks.
   */
  events?: DialogEventNames;
  /** The rendered `FormView`. */
  children: React.ReactNode;
}
//#endregion
//#region assets/crud/view/viewMode.d.ts
interface ResolvedViewMode {
  mode: CrudViewMode;
  drawerWidth: number | string;
  drawerSide: 'right' | 'left';
}
/**
 * Normalises the `viewMode` field of `ResourceConfig` into a fully-resolved
 * object with defaults applied. Accepts the shorthand string form
 * (`'dialog' | 'drawer' | 'page'`) or the full config object.
 */
declare function resolveViewMode(raw: CrudViewMode | CrudViewModeConfig | undefined): ResolvedViewMode;
//#endregion
//#region assets/crud/view/CrudFormShell.d.ts
interface CrudFormShellPropsWithMode extends CrudFormShellProps {
  viewMode: ResolvedViewMode;
}
/**
 * Dispatches the form rendering to the correct shell based on the resource's
 * `viewMode`. All three shells consume the same `CrudFormShellProps`, so the
 * orchestrator (`CrudPage`) never has to know which shell is active.
 */
declare const CrudFormShell: React.FC<CrudFormShellPropsWithMode>;
//#endregion
//#region assets/crud/view/CrudDialogShell.d.ts
/**
 * Dialog form shell — preserves the legacy CRUD UX.
 * Thin wrapper around `CrudDialogView` so the orchestrator can stay shell-agnostic.
 */
declare const CrudDialogShell: React.FC<CrudFormShellProps>;
//#endregion
//#region assets/crud/view/CrudDrawerShell.d.ts
/**
 * Drawer form shell — slides in from the side and leaves the grid visible.
 *
 * - Default: right-side panel `min(720px, 60vw)` wide.
 * - Mobile (≤ 768px): becomes a full-screen sheet (no side gap, no animation jank).
 * - Escape closes the drawer; nested lookup dialogs float above via z-index.
 */
declare const CrudDrawerShell: React.FC<CrudFormShellProps>;
//#endregion
//#region assets/crud/view/CrudPageShell.d.ts
/**
 * Page form shell — renders inline (no portal) and is expected to replace the
 * grid view via the `wrapper--with-page` CSS hook set by `CrudPage`.
 *
 * URL deep-linking is already wired through `useRouting`; the shell itself
 * does not navigate — it just exposes Back/Cancel/Save callbacks that the
 * orchestrator maps to `navigate()` calls or close-dialog dispatches.
 */
declare const CrudPageShell: React.FC<CrudFormShellProps>;
//#endregion
//#region assets/crud/crud/ToolbarSelect.d.ts
type ToolbarSelectOption = AppDropdownOption;
interface ToolbarSelectProps {
  id: string;
  label: string;
  icon?: string;
  value: string;
  options: ToolbarSelectOption[];
  onChange: (value: string) => void;
  disabled?: boolean;
}
/** @deprecated Use `AppDropdown` with `variant="toolbar"` from `@nubit/ui`. */
declare function ToolbarSelect({
  id,
  label,
  icon,
  value,
  options,
  onChange,
  disabled
}: ToolbarSelectProps): import("react").JSX.Element;
//#endregion
//#region assets/crud/crud/ColumnPresetSelector.d.ts
interface ColumnPresetSelectorProps {
  resourceId: string;
  presets: ColumnPreset[];
  activePreset: string | null;
  onPresetChange: (key: string | null) => void;
  columnsLabel: string;
  allColumnsLabel: string;
}
declare function ColumnPresetSelector({
  resourceId,
  presets,
  activePreset,
  onPresetChange,
  columnsLabel,
  allColumnsLabel
}: ColumnPresetSelectorProps): import("react").JSX.Element;
//#endregion
//#region assets/crud/crud/useColumnPreset.d.ts
interface ColumnPresetState {
  activePreset: string | null;
  setPreset: (key: string | null) => void;
  visibleColumns: string[] | null;
}
declare function useColumnPreset(resource: ResourceConfig): ColumnPresetState;
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
interface ResourceSchemaProviderProps {
  children: React.ReactNode;
  resolver: ResourceSchemaResolver;
}
declare function ResourceSchemaProvider({
  children,
  resolver
}: ResourceSchemaProviderProps): React.JSX.Element;
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
  children: React.ReactNode;
  factory: ResourceStoreFactory;
}
declare function ResourceStoreProvider({
  children,
  factory
}: ResourceStoreProviderProps): React.JSX.Element;
declare function useResourceStoreFactory(): ResourceStoreFactory;
//#endregion
export { type AuditEntry, type AuditTrailConfig, AuditTrailPanel, type AuditTrailPanelProps, type BackendAdapter, type BulkAction, type ColumnPreset, ColumnPresetSelector, type ColumnPresetState, CrudDialogShell, CrudDrawerShell, type CrudDrawerViewEvents, type CrudDrawerViewOptions, CrudFormShell, type CrudFormShellProps, CrudPage, CrudPageShell, type CrudViewMode, type CrudViewModeConfig, DATA_GRID_EVENTS, type DataGridSelectionChangedEvent, type DataGridSummaryItem, NativeDataGridView as DataGridView, type DataGridViewOptions, type DataRecord, type DetailSummaryOptions, CrudDialogView as DialogView, CrudDrawerView as DrawerView, type EnumOption, FORM_EVENTS, type Field, FieldBuilder, type FieldDef, type FieldOverride, FieldType, type FilterRule, type FormHandle, type FormLayout, type FormOnChangeFn, type FormSection, type FormTab, NativeFormView as FormView, type FormViewOptions, type FormatterFn, type GridCellContext, type GridData, type GridHandle, type GridOnChangeFn, HydraAdapter, type ItemFormatterFn, type LoadOption, type OnChangeFn, type ResolvedViewMode, type ResourceConfig, type ResourceEmptyState, type ResourceFilterDescriptor, type ResourceFilterRule, type ResourceFormDetail, type ResourceGridDetail, type ResourceLoadOption, type ResourceLoadOptions, type ResourcePermissions, type ResourceRouting, type ResourceRowActions, ResourceSchemaProvider, type ResourceSchemaProviderProps, type ResourceSchemaResolution, type ResourceSchemaResolver, type ResourceSortDescriptor, type ResourceStore, type ResourceStoreFactory, type ResourceStoreOptions, ResourceStoreProvider, type ResourceStoreProviderProps, type ResourceToolbar, type ResourceToolbarAction, type ResourceToolbarActionVariant, type ResourceToolbarContext, type ResourceToolbarItems, RestAdapter, type SmartCrudFieldContract, type SmartCrudFieldOperation, type SmartCrudFieldPatch, type SmartCrudHydraFieldContract, type SmartCrudHydraFieldDirective, type SmartCrudManualField, type SmartCrudManualFieldContract, type SmartCrudOperation, SmartCrudPage, SmartCrudRolesProvider, type SummaryCalculateContext, type SummaryFormat, type SummaryItem, type SummaryTextContext, type SummaryType, ToolbarSelect, type ToolbarSelectOption, type ToolbarSelectProps, type ValidationRule, checkboxField, computeSummaryValue, createCrudEvents, currencyField, dateField, datetimeField, defineFieldContract, defineFields, defineResource, entityField, enumField, fileField, formatSummaryValue, identityField, imageField, noneField, numberField, passwordField, resolveSummaryText, resolveViewMode, selectField, switchField, textField, textareaField, useColumnPreset, useResourceStoreFactory, useSmartCrudRoles, validateFieldContract };