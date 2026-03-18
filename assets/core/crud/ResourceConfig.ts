import type { RefObject } from 'react';
import type { Field, FieldDef } from '../field/Field';
import type { DxFilterRule } from '../common/FilterRule';
import type { FormEventNames, ToolbarButtonItem } from '../event/EventTypes';
import type { DxFormEvents } from '../form/DxForm';
import type { BulkAction } from './BulkAction';
import type { FormLayout } from './FormLayout';
import type { ColumnPreset } from './ColumnPreset';
import type { AuditTrailConfig } from './AuditTrail';

/**
 * Generic factory that receives a form ref and returns typed field definitions.
 * Defaults to `Record<string, unknown>` for backward compatibility.
 */
export type FieldsFactory<T extends Record<string, unknown> = Record<string, unknown>> = (
  formRef: RefObject<DxFormEvents | null>,
) => FieldDef<T>[];

/**
 * URL-based deep-linking and filter sync configuration for SmartCrudPage.
 * All fields are optional — if `routing` is absent, no URL behaviour is applied.
 */
export interface ResourceRouting {
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
export interface ResourcePermissions {
  canAdd?: boolean | (() => boolean);
  canEdit?: boolean | (() => boolean);
  canDelete?: boolean | (() => boolean);
  canExport?: boolean | (() => boolean);
  canBulkDelete?: boolean | (() => boolean);
}

export interface ResourceConfig<T extends Record<string, unknown> = Record<string, unknown>> {
  id: string;
  title: string;
  apiUrl: string;
  fields: Field[] | FieldDef<T>[] | FieldsFactory<T>;
  /** Optional: inject a pre-existing event bus (e.g. a module-level singleton).
   *  When omitted, `useCrudPage` creates fresh events via `createCrudEvents(id)`. */
  events?: FormEventNames;
  detailFields?: Field[];
  detailUrl?: string;
  detailPropertyName?: string;
  sort?: Array<{ selector: string; desc: boolean }>;
  filter?: DxFilterRule[];
  mode?: 'normal' | 'minimal';
  paging?: boolean;
  stateStoring?: boolean;
  allowAdd?: boolean;
  allowEdit?: boolean;
  allowDelete?: boolean;
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
   * DevExtreme editing mode.
   * - `'popup'` (default) — opens DxDialog + DxForm.
   * - `'row'` | `'cell'` | `'batch'` — inline DevExtreme editing (no DxDialog).
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
   * @param formRef - ref to the DxForm instance (call `formRef.current?.setFieldValue(...)` etc.)
   */
  onDetailRowsChanged?: (formRef: RefObject<DxFormEvents | null>) => void;
  /**
   * Internal: HTTP methods inferred from `hydra:supportedOperation` in the API doc.
   * Populated automatically by SmartCrudPage from useResourceSchema.
   * Do NOT set this manually — it is overridden by any explicit `allowX` / `permissions` config.
   * e.g. ['GET', 'POST', 'PATCH', 'DELETE']
   */
  _supportedOperations?: string[];
}
