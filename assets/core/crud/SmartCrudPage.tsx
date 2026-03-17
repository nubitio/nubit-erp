import React, { useState, useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { CrudPage } from './CrudPage';
import { useResourceSchema } from './hydra/useResourceSchema';
import type { ResourceConfig } from './ResourceConfig';
import type { Field } from '../field/Field';
import { useAuth } from '../../contexts/auth';
import { useFieldPermissions } from './rules/useFieldPermissions';
import { useConditionalRules } from './rules/useConditionalRules';
import { useDependsOn } from './rules/useDependsOn';
import { useRouting } from './routing/useRouting';

// ---------------------------------------------------------------------------
// Internal presentational helpers
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// SmartCrudPage
// ---------------------------------------------------------------------------

interface SmartCrudPageProps<T extends Record<string, unknown> = Record<string, unknown>> {
  resource: ResourceConfig<T>;
}

/**
 * SmartCrudPage — wraps CrudPage with Hydra auto-discovery and declarative rules.
 *
 * ## Field resolution (post SLICE-005)
 * - If `resource.fields` is a non-empty array, it is used as-is (pass-through).
 * - If `resource.fields` is empty / absent, fields are inferred from the Hydra
 *   API spec at `/api/docs.json` via `useResourceSchema`.
 * - While the schema loads: renders a skeleton loader.
 * - If the schema fetch errors: renders a user-visible error with a retry button.
 *
 * ## Declarative rules (SLICE-006 Wave 2)
 * After resolving fields, the following pipeline runs:
 *   1. `useFieldPermissions` — RBAC: hides or disables fields based on user roles
 *   2. `useConditionalRules` — evaluates visibleWhen / disabledWhen / computed
 *   3. `useDependsOn`        — invalidates entity queries when dependency fields change
 *   4. `useRouting`          — deep-link and URL filter sync (opt-in via resource.routing)
 *
 * `formData` is kept live via the `onFormDataChange` callback now available on CrudPage,
 * so visibleWhen / disabledWhen / computed rules react to actual in-form changes.
 *
 * URL deep-linking is wired via `initialRecordId` / `initialIsNew` props on CrudPage.
 */
export function SmartCrudPage<T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
}: SmartCrudPageProps<T>) {
  const queryClient = useQueryClient();

  // ---------------------------------------------------------------------------
  // Step 1: Resolve fields (Hydra auto-discovery or pass-through)
  // ---------------------------------------------------------------------------

  // Determine whether the consumer supplied manual fields.
  // `fields` can be Field[], FieldDef<T>[], or FieldsFactory<T> (function).
  // We only treat it as "manual" when it is a non-empty static array.
  const hasManualFields =
    Array.isArray(resource.fields) && (resource.fields as unknown[]).length > 0;

  const { fields, isLoading, error } = useResourceSchema(
    resource.apiUrl,
    // Pass the manual fields only when they exist so useResourceSchema short-circuits
    hasManualFields ? (resource.fields as Field[]) : undefined,
  );

  // ---------------------------------------------------------------------------
  // Step 2: Auth — get user roles for RBAC field filtering
  // ---------------------------------------------------------------------------

  const { user } = useAuth();
  const userRoles: string[] = user?.roles ?? [];

  // ---------------------------------------------------------------------------
  // Step 3: Declarative rules pipeline
  // ---------------------------------------------------------------------------

  // Live form data — updated via onFormDataChange callback wired to CrudPage.
  // Enables reactive evaluation of visibleWhen / disabledWhen / computed rules.
  const [formData, setFormData] = useState<Record<string, unknown>>({});

  // Stable callback reference — avoids re-rendering CrudPage on every setState call
  const handleFormDataChange = useCallback((data: Record<string, unknown>) => {
    setFormData(data);
  }, []);

  // 3a. RBAC field filtering: hide or force-disable based on user roles
  const permissionFilteredFields = useFieldPermissions(fields ?? [], userRoles);

  // 3b. Conditional visibility / disabled / computed rules
  const fieldStates = useConditionalRules(permissionFilteredFields, formData);

  // 3c. Reactive entity query invalidation for dependsOn fields
  useDependsOn(permissionFilteredFields, formData);

  // ---------------------------------------------------------------------------
  // Step 4: URL routing (opt-in)
  // ---------------------------------------------------------------------------

  // Provides initialRecordId, initialIsNew, initialFilters, syncFilters.
  // These are now forwarded to CrudPage as props for deep-link dialog opening.
  const routingState = useRouting(resource.routing);

  // ---------------------------------------------------------------------------
  // Step 5: Apply FieldStates to the resolved fields
  // ---------------------------------------------------------------------------

  const processedFields: Field[] = React.useMemo(() => {
    return permissionFilteredFields
      .map((field) => {
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

  // ---------------------------------------------------------------------------
  // Loading / error states (only for Hydra auto-discovery)
  // ---------------------------------------------------------------------------

  if (!hasManualFields && isLoading) {
    return <CrudSkeleton />;
  }

  if (!hasManualFields && error) {
    return (
      <CrudError
        message={error.message}
        onRetry={() => queryClient.invalidateQueries({ queryKey: ['/api/docs.json'] })}
      />
    );
  }

  // ---------------------------------------------------------------------------
  // Step 6: Render CrudPage with processed fields
  // ---------------------------------------------------------------------------

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
