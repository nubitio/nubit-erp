/**
 * @nubit/react-admin — batteries-included admin stack.
 *
 * Umbrella re-export for fast project bootstrap. Import everything you need
 * for a typical API Platform admin app from this single package:
 *
 *   import { CoreProvider, CoreConfigProvider, CrudPage, AdminShell, SchemaProvider } from '@nubit/react-admin';
 *
 * Peer dependencies: react, react-dom, react-router-dom, @tanstack/react-query,
 *                    i18next, react-i18next, luxon.
 */

// ── Runtime setup ─────────────────────────────────────────────────────────────
export {
  CoreProvider,
  CoreConfigProvider,
  useCoreConfig,
  configureCoreDate,
} from '@nubit/core';
export type { CoreProviderProps, CoreConfigProviderProps, CoreConfig } from '@nubit/core';

// ── HTTP ──────────────────────────────────────────────────────────────────────
export { useCoreHttpClient, createCoreHttpClient } from '@nubit/core';
export type { CoreHttpClient, CoreHttpClientConfig } from '@nubit/core';

// ── CRUD engine ───────────────────────────────────────────────────────────────
export {
  CrudPage,
  SmartCrudPage,
  SmartCrudRolesProvider,
  defineResource,
  defineFields,
  defineFieldContract,
  useCrudPage,
  usePermissions,
  useSelectionState,
  useSmartCrudRoles,
  initCoreI18n,
  useCoreTranslation,
  resolveSmartCrudFields,
} from '@nubit/core';
export type {
  ResourceConfig,
  ResourcePermissions,
  ResolvedPermissions,
  BulkAction,
  ColumnPreset,
  FormLayout,
  FormSection,
  FormTab,
  SelectionState,
  FieldOverride,
  SmartCrudFieldContract,
  SmartCrudRecord,
  AuditEntry,
  AuditTrailConfig,
} from '@nubit/core';

// ── Field DSL ─────────────────────────────────────────────────────────────────
export {
  textField,
  textareaField,
  numberField,
  currencyField,
  dateField,
  datetimeField,
  entityField,
  enumField,
  selectField,
  checkboxField,
  switchField,
  imageField,
  passwordField,
  identityField,
  noneField,
  FieldBuilder,
} from '@nubit/core';
export type { Field, FieldDef, LoadOption, EnumOption } from '@nubit/core';
export type {
  FormatterFn,
  GridCellContext,
  GridOnChangeFn,
  FormOnChangeFn,
  OnChangeFn,
} from '@nubit/core';

// ── Form & grid handles ───────────────────────────────────────────────────────
export type { FormHandle } from '@nubit/core';
export type { GridHandle } from '@nubit/core';
export { FormView, FORM_EVENTS } from '@nubit/core';
export { DataGridView, DATA_GRID_EVENTS } from '@nubit/core';
export { DialogView } from '@nubit/core';

// ── Event bus ─────────────────────────────────────────────────────────────────
export { dispatch, useEvents, createCrudEvents } from '@nubit/core';
export type { EventSubscription } from '@nubit/core';

// ── Locale / date ─────────────────────────────────────────────────────────────
export { DateUtils, LIMA_TZ, getCoreLocale, getCoreTimezone } from '@nubit/core';

// ── Data ──────────────────────────────────────────────────────────────────────
export { ApiPlatformRemoteDataSource } from '@nubit/core';
export type { DataRecord } from '@nubit/core';
export type {
  RemoteDataSourceOptions,
  RemoteFilterDescriptor,
  RemoteLoadOptions,
  RemoteSortDescriptor,
} from '@nubit/core';

// ── Mercure (real-time) ───────────────────────────────────────────────────────
export { MercureProvider, useMercureHub, useMercureSubscription } from '@nubit/core';
export type { MercureProviderProps } from '@nubit/core';

// ── UI primitives ─────────────────────────────────────────────────────────────
export { AppDialog, ConfirmDialog } from '@nubit/ui';
export type { AppDialogProps, ConfirmDialogProps } from '@nubit/ui';

// ── API Platform / Hydra adapter ──────────────────────────────────────────────
export {
  SchemaProvider,
  useSchemaContext,
  useHydraMetadata,
  useResourceSchema,
  parseHydraDoc,
  parseOpenApiDoc,
  mapHydraSchemaToFields,
  resolveRangeTag,
} from '@nubit/api-platform';
export type {
  UseSchemaContextResult,
  HydraApiDoc,
  OpenApiDoc,
  ApiDoc,
} from '@nubit/api-platform';

// ── Admin shell ───────────────────────────────────────────────────────────────
export {
  AdminShell,
  AdminHeader,
  AdminSidebarMenu,
  useScreenSize,
  useScreenSizeClass,
} from '@nubit/admin';
export type {
  AdminShellProps,
  AdminMenuItem,
  AdminHeaderAction,
  AdminHeaderProps,
  AdminSidebarMenuProps,
  AdminMenuSubItem,
  AdminSidebarMenuSelectEvent,
} from '@nubit/admin';
