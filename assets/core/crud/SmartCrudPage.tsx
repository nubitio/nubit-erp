import React, { useState, useCallback, useMemo, useEffect, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { CrudPage } from './CrudPage';
import { useResourceSchema } from './hydra/useResourceSchema';
import { API_DOC_QUERY_KEY } from './hydra/useHydraMetadata';
import type { ResourceConfig } from './ResourceConfig';
import type { Field } from '../field/Field';
import type { FieldOverride } from './hydra/useResourceSchema';
import { useAuth } from '../../contexts/auth';
import { useFieldPermissions } from './rules/useFieldPermissions';
import { useConditionalRules } from './rules/useConditionalRules';
import { useDependsOn } from './rules/useDependsOn';
import { useRouting } from './routing/useRouting';
import { logDevHint } from './devHint';

function CrudSkeleton() {
  return (
    <div className="p-6 space-y-4 animate-pulse">
      <div className="h-8 bg-gray-200 rounded w-1/3" />
      <div className="h-64 bg-gray-200 rounded" />
    </div>
  );
}

function CrudError({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="p-6 text-red-600 space-y-2">
      <p className="font-semibold">Failed to load resource schema</p>
      <p className="text-sm">{message}</p>
      <button onClick={onRetry} className="text-blue-600 underline text-sm" type="button">
        Retry
      </button>
    </div>
  );
}

interface SmartCrudPageProps<T extends Record<string, unknown> = Record<string, unknown>> {
  resource: ResourceConfig<T>;
  /**
   * Per-field overrides applied on top of auto-inferred Hydra fields.
   * Each entry must contain a `key` matching the field's `name`, plus any
   * `Field` properties to override (e.g. `{ key: 'name', label: 'Custom' }`).
   * Has no effect when `resource.fields` is a non-empty manual array.
   */
  fieldOverrides?: FieldOverride[];
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
 * ## Declarative rules pipeline
 *   1. `useFieldPermissions` — RBAC: hides or disables fields based on user roles
 *   2. `useConditionalRules` — evaluates visibleWhen / disabledWhen / computed
 *   3. `useDependsOn`        — invalidates entity queries when dependency fields change
 *   4. `useRouting`          — deep-link and URL filter sync (opt-in via resource.routing)
 *
 * `formData` is kept live via the `onFormDataChange` callback so visibleWhen /
 * disabledWhen / computed rules react to actual in-form changes.
 *
 * URL deep-linking is wired via `initialRecordId` / `initialIsNew` props on CrudPage.
 */
export function SmartCrudPage<T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
  fieldOverrides,
}: SmartCrudPageProps<T>) {
  const queryClient = useQueryClient();

  // Determine whether the consumer supplied manual fields.
  // `fields` can be Field[], FieldDef<T>[], or FieldsFactory<T> (function).
  // We only treat it as "manual" when it is a non-empty static array.
  const hasManualFields =
    Array.isArray(resource.fields) && (resource.fields as unknown[]).length > 0;

  const { fields, isLoading, error, supportedOperations } = useResourceSchema(
    resource.apiUrl,
    // Pass the manual fields only when they exist so useResourceSchema short-circuits
    hasManualFields ? (resource.fields as Field[]) : undefined,
    // Pass fieldOverrides through to useResourceSchema's merge logic
    hasManualFields ? undefined : fieldOverrides,
  );

  // Dev hint: log a defineResource() snippet once per resource URL when fields
  // were auto-inferred (not supplied manually). No-op in production.
  // useEffect runs after render to avoid accessing refs during render.
  const hintFiredRef = useRef(false);
  useEffect(() => {
    if (!hasManualFields && !isLoading && !error && fields.length > 0 && !hintFiredRef.current) {
      hintFiredRef.current = true;
      logDevHint(resource.apiUrl, fields);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasManualFields, isLoading, error, fields, resource.apiUrl]);

  const { user } = useAuth();
  // Stabilize the roles array so useMemo inside useFieldPermissions is not
  // defeated by a new array reference being created on every render when
  // user?.roles is undefined.
  const stableRoles = useMemo(() => user?.roles ?? [], [user?.roles]);

  // Live form data — updated via onFormDataChange callback wired to CrudPage.
  // Enables reactive evaluation of visibleWhen / disabledWhen / computed rules.
  const [formData, setFormData] = useState<Record<string, unknown>>({});

  // Stable callback — avoids re-rendering CrudPage on every setState call
  const handleFormDataChange = useCallback((data: Record<string, unknown>) => {
    setFormData(data);
  }, []);

  // 3a. RBAC field filtering
  const permissionFilteredFields = useFieldPermissions(fields ?? [], stableRoles);
  const fieldStates = useConditionalRules(permissionFilteredFields, formData);
  useDependsOn(permissionFilteredFields, formData);

  const routingState = useRouting(resource.routing);

  const processedFields: Field[] = React.useMemo(() => {
    return permissionFilteredFields
      .map((field) => {
        // Identity fields (e.g. the hidden "id" field) must always be present in the
        // field list passed to DxDataGrid so that DevExtreme can resolve keyExpr.
        // Conditional rules and visibility state are never applied to them.
        if (field.isIdentity) return field;

        const state = fieldStates.find((s) => s.name === field.name);
        if (!state) return field;

        // If the field should not be visible, we will filter it out below
        if (!state.visible) return null;

        const updated: Field = { ...field };

        // Apply disabled state
        if (state.disabled) {
          updated.disabled = true;
        }

        // Apply computed value: mark field as readonly and set a default value
        // (CrudPage/DxForm reads field.defaultValue for initial form data,
        // and field.readonly prevents user editing)
        if (state.computedValue !== undefined) {
          updated.readonly = true;
          updated.defaultValue = state.computedValue as Field['defaultValue'];
        }

        return updated;
      })
      .filter((f): f is Field => f !== null);
  }, [permissionFilteredFields, fieldStates]);

  if (!hasManualFields && isLoading) {
    return <CrudSkeleton />;
  }

  if (!hasManualFields && error) {
    return (
      <CrudError
        message={error.message}
        onRetry={() => queryClient.invalidateQueries({ queryKey: API_DOC_QUERY_KEY })}
      />
    );
  }

  // Normalize apiUrl — ensure it always starts with '/' so axios treats it as
  // an absolute path regardless of the current page URL.
  // e.g. CategoryPage passes 'api/categories' (no leading slash); without this
  // normalization the browser resolves it relative to the current page, which
  // produces incorrect URLs like '/some-page/api/categories'.
  const normalizedApiUrl = resource.apiUrl.startsWith('/')
    ? resource.apiUrl
    : `/${resource.apiUrl}`;

  const resolvedResource: ResourceConfig<T> = {
    ...(hasManualFields ? resource : { ...resource, fields }),
    apiUrl: normalizedApiUrl,
    fields: processedFields,
    _supportedOperations: supportedOperations,
  };

  return (
    <CrudPage
      resource={resolvedResource}
      onFormDataChange={handleFormDataChange}
      initialRecordId={routingState.initialRecordId}
      initialIsNew={routingState.initialIsNew}
    />
  );
}
