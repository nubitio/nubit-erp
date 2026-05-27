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
  ResourceSchemaProvider,
  ResourceStoreProvider,
  useSelectionState,
  useResolvedResourceFields,
  useResourceStoreFactory,
  useSmartCrudRoles,
  initCoreI18n,
  useCoreTranslation,
  resolveSmartCrudFields,
} from '@nubit/crud';
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
  ResourceSchemaResolver,
  ResourceStore,
  ResourceStoreFactory,
  FieldOverride,
  SmartCrudFieldContract,
  AuditEntry,
  AuditTrailConfig,
} from '@nubit/crud';

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
} from '@nubit/crud';
export type { Field, FieldDef, LoadOption, EnumOption } from '@nubit/crud';
export type {
  FormatterFn,
  GridCellContext,
  GridOnChangeFn,
  FormOnChangeFn,
  OnChangeFn,
} from '@nubit/crud';

// ── Form & grid handles ───────────────────────────────────────────────────────
export type { FormHandle } from '@nubit/crud';
export type { GridHandle } from '@nubit/crud';
export { FormView, FORM_EVENTS } from '@nubit/crud';
export { DataGridView, DATA_GRID_EVENTS } from '@nubit/crud';
export { DialogView } from '@nubit/crud';

// ── Event bus ─────────────────────────────────────────────────────────────────
export { dispatch, useEvents } from '@nubit/core';
export { createCrudEvents } from '@nubit/crud';
export type { EventSubscription } from '@nubit/core';

// ── Locale / date ─────────────────────────────────────────────────────────────
export { DateUtils, getCoreLocale, getCoreTimezone } from '@nubit/core';

// ── Data ──────────────────────────────────────────────────────────────────────
export type { DataRecord } from '@nubit/core';

// ── Hydra Data Source ─────────────────────────────────────────────────────────
export { HydraRemoteDataSource } from '@nubit/hydra';
export type {
  RemoteDataSourceOptions,
  RemoteFilterDescriptor,
  RemoteLoadOptions,
  RemoteSortDescriptor,
} from '@nubit/hydra';

// ── Mercure (real-time) ───────────────────────────────────────────────────────
export { MercureProvider, useMercureHub, useMercureSubscription } from '@nubit/core';
export type { MercureProviderProps } from '@nubit/core';

// ── UI primitives ─────────────────────────────────────────────────────────────
export { AppDialog, ConfirmDialog, AppToolbar, Card, ThemeProvider, ThemeContext, useTheme, ThemeSwitcher } from '@nubit/ui';
export type { AppDialogProps, ConfirmDialogProps, AppToolbarProps, CardProps, ThemeProviderProps, ThemeContextValue, Theme, ThemeMode, ThemeSwitcherProps } from '@nubit/ui';

// ── Hydra/OpenAPI adapter ─────────────────────────────────────────────────────
export {
  SchemaProvider,
  useSchemaContext,
  useHydraMetadata,
  useResourceSchema,
  HydraResourceSchemaProvider,
  HydraResourceStoreProvider,
  parseHydraDoc,
  parseOpenApiDoc,
  mapHydraSchemaToFields,
  resolveRangeTag,
} from '@nubit/hydra';
export type {
  UseSchemaContextResult,
  HydraApiDoc,
  OpenApiDoc,
  ApiDoc,
} from '@nubit/hydra';

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
