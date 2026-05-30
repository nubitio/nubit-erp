/**
 * @nubit/crud — CRUD engine, field DSL, form/grid views.
 *
 * Core entry point for building admin CRUD pages against any REST or Hydra backend.
 *
 * Quick start:
 *   const resource = defineResource('/api/products', { title: 'Products' });
 *   export const ProductPage = () => <SmartCrudPage resource={resource} />;
 *
 * defineResource(apiUrl, overrides?) is the only call form — the legacy object overload is gone.
 */

// ── CRUD engine ───────────────────────────────────────────────────────────────
export {
  CrudPage,
  SmartCrudPage,
  SmartCrudRolesProvider,
  useSmartCrudRoles,
  defineFieldContract,
  defineFields,
  defineResource,
  validateFieldContract,
  createCrudEvents,
  AuditTrailPanel,
} from './crud';
export type {
  AuditEntry,
  AuditTrailConfig,
  AuditTrailPanelProps,
  BulkAction,
  ColumnPreset,
  FormLayout,
  FormSection,
  FormTab,
  ResourceConfig,
  ResourceFormDetail,
  ResourceGridDetail,
  ResourcePermissions,
  ResourceRowActions,
  ResourceRouting,
  ResourceToolbar,
  ResourceToolbarAction,
  ResourceToolbarActionVariant,
  ResourceToolbarContext,
  ResourceToolbarItems,
  SmartCrudFieldContract,
  SmartCrudFieldOperation,
  SmartCrudFieldPatch,
  SmartCrudHydraFieldContract,
  SmartCrudHydraFieldDirective,
  SmartCrudManualField,
  SmartCrudManualFieldContract,
  SmartCrudOperation,
} from './crud';

// ── Field DSL ─────────────────────────────────────────────────────────────────
export {
  currencyField,
  dateField,
  datetimeField,
  entityField,
  enumField,
  checkboxField,
  identityField,
  imageField,
  noneField,
  numberField,
  passwordField,
  selectField,
  switchField,
  textField,
  textareaField,
} from './field/FieldBuilders';
export type { EnumOption } from './field/FieldBuilders';
export { FieldBuilder } from './field/FieldBuilder';
export { FieldType } from './field/FieldType';
export type { Field, FieldDef, LoadOption } from './field/Field';
export type {
  FormatterFn,
  FormOnChangeFn,
  GridCellContext,
  GridOnChangeFn,
  ItemFormatterFn,
  OnChangeFn,
} from './field/FieldCallbacks';
export type { FilterRule } from './field/FilterRule';
export type { ValidationRule } from './field/ValidationRule';

// ── Views ─────────────────────────────────────────────────────────────────────
export { DataGridView, DATA_GRID_EVENTS } from './datagrid/DataGridView';
export { DialogView } from './dialog/DialogView';
export { FormView, FORM_EVENTS } from './form/FormView';

// ── Handles & options ─────────────────────────────────────────────────────────
export type { FormHandle } from './form/FormHandle';
export type { GridHandle } from './datagrid/GridHandle';
export type {
  DataGridSelectionChangedEvent,
  DataGridSummaryItem,
  DataGridViewOptions,
} from './datagrid/DataGridViewOptions';

// ── Backend adapters ──────────────────────────────────────────────────────────
export type { BackendAdapter } from './adapter/BackendAdapter';
export { HydraAdapter } from './adapter/HydraAdapter';
export { RestAdapter } from './adapter/RestAdapter';

// ── Extension points (for @nubit/hydra and adapter implementors) ──────────────
export {
  ResourceSchemaProvider,
} from './schema/ResourceSchema';
export type {
  ResourceSchemaProviderProps,
  ResourceSchemaResolution,
  ResourceSchemaResolver,
} from './schema/ResourceSchema';
export {
  ResourceStoreProvider,
} from './data/ResourceStore';
export type { FieldOverride } from './crud/resolveSmartCrudFields';
export {
  useResourceStoreFactory,
} from './data/ResourceStore';
export type {
  ResourceFilterDescriptor,
  ResourceFilterRule,
  ResourceLoadOption,
  ResourceLoadOptions,
  ResourceSortDescriptor,
  ResourceStore,
  ResourceStoreFactory,
  ResourceStoreOptions,
  ResourceStoreProviderProps,
} from './data/ResourceStore';

// ── Base data types (convenience re-exports from @nubit/core) ─────────────────
export type { DataRecord } from '@nubit/core';
export type { GridData } from '@nubit/core';
