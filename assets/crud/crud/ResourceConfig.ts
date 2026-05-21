import type { RefObject } from 'react';
import type { Field, FieldDef } from '../field/Field';
import type { FilterRule } from '../field/FilterRule';
import type { FormEventNames, ToolbarButtonItem } from '../../core/event/EventTypes';
import type { FormHandle } from '../form/FormHandle';
import type { FormLayout } from '../form/FormLayout';
import type { BulkAction } from './BulkAction';
import type { ColumnPreset } from './ColumnPreset';
import type { AuditTrailConfig } from './AuditTrail';
import type { SmartCrudFieldContract } from './fieldContract';
import type { DataRecord } from '@nubit/core';

/**
 * Generic factory that receives a form ref and returns typed field definitions.
 * Defaults to `DataRecord` for backward compatibility.
 */
export type FieldsFactory<T extends DataRecord = DataRecord> = (
  formRef: RefObject<FormHandle | null>,
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

export interface ResourceConfig<T extends DataRecord = DataRecord> {
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
  sort?: Array<{ selector: string; desc: boolean }>;
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
