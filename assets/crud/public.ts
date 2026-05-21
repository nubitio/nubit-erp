/**
 * @nubit/crud — CRUD engine, field DSL, form/grid views.
 *
 * Extracted from @nubit/core. All CRUD-layer abstractions live here;
 * @nubit/core retains only runtime primitives (HTTP, Mercure, config, events).
 *
 * Convenience re-exports of base data types from @nubit/core are included
 * so CRUD consumers can use a single import.
 */

// ── CRUD engine ───────────────────────────────────────────────────────────────
export {
  CrudPage,
  SmartCrudPage,
  SmartCrudRolesProvider,
  createScopedEventBus,
  defineFieldContract,
  defineFields,
  defineResource,
  initCoreI18n,
  resolveSmartCrudFields,
  mergeOverrides,
  useCoreTranslation,
  useCrudPage,
  usePermissions,
  useSelectionState,
  useSmartCrudRoles,
} from '../core/crud';
export type {
  AuditEntry,
  AuditTrailConfig,
  BulkAction,
  ColumnPreset,
  FieldOverride,
  FormLayout,
  FormSection,
  FormTab,
  ResourceConfig,
  ResourcePermissions,
  ResourceRouting,
  ResolvedPermissions,
  SelectionState,
  SmartCrudFieldContract,
  SmartCrudFieldOperation,
  SmartCrudFieldOperationBehavior,
  SmartCrudFieldOperationFlag,
  SmartCrudFieldOperationState,
  SmartCrudFieldPatch,
  SmartCrudFieldResolutionStrategy,
  SmartCrudFieldSourceMode,
  SmartCrudHydraFieldContract,
  SmartCrudHydraFieldDirective,
  SmartCrudManualField,
  SmartCrudManualFieldContract,
  SmartCrudModelFieldKey,
  SmartCrudOperation,
  SmartCrudRecord,
} from '../core/crud';

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
} from '../core/field/FieldBuilders';
export type { EnumOption } from '../core/field/FieldBuilders';
export { FieldBuilder } from '../core/field/FieldBuilder';
export { FieldType } from '../core/field/FieldType';
export type { Field, FieldDef, LoadOption } from '../core/field/Field';
export type {
  FormatterFn,
  FormOnChangeFn,
  GridCellContext,
  GridOnChangeFn,
  ItemFormatterFn,
  OnChangeFn,
} from '../core/field/FieldCallbacks';
export type { FilterRule } from '../core/field/FilterRule';
export type { ValidationRule } from '../core/field/ValidationRule';

// ── Views ─────────────────────────────────────────────────────────────────────
export { DataGridView, DATA_GRID_EVENTS } from '../core/datagrid/DataGridView';
export { DialogView } from '../core/dialog/DialogView';
export { FormView, FORM_EVENTS } from '../core/form/FormView';

// ── Handles & options ─────────────────────────────────────────────────────────
export type { FormHandle } from '../core/form/FormHandle';
export type { GridHandle } from '../core/datagrid/GridHandle';
export type {
  DataGridSelectionChangedEvent,
  DataGridSummaryItem,
  DataGridViewOptions,
} from '../core/datagrid/DataGridViewOptions';

// ── CRUD events ───────────────────────────────────────────────────────────────
export { createCrudEvents } from '../core/event/createCrudEvents';

// ── UI dialogs (convenience re-exports from @nubit/ui) ────────────────────────
export { ConfirmDialog } from '@nubit/ui';
export type { ConfirmDialogProps } from '@nubit/ui';
export { AppDialog } from '@nubit/ui';
export type { AppDialogProps } from '@nubit/ui';

// ── Base data types (convenience re-exports from @nubit/core) ─────────────────
export type { DataRecord } from '@nubit/core';
export type { GridData } from '@nubit/core';
