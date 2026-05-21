import React, { useMemo, useEffect, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { CrudPage } from './CrudPage';
import { useResourceSchema } from './hydra/useResourceSchema';
import { API_DOC_QUERY_KEY } from './hydra/useHydraMetadata';
import type { ResourceConfig } from './ResourceConfig';
import type { Field } from '../field/Field';
import type { FieldOverride } from './hydra/useResourceSchema';
import { useRouting } from './routing/useRouting';
import { logDevHint } from './devHint';
import { dispatch, useMercureSubscription } from '@nubit/core';
import { resolveCrudResource } from './resolveCrudResource';
import { useSmartCrudRoles } from './SmartCrudRolesContext';
import { useSmartCrudOperation } from './useSmartCrudOperation';
import { useSmartCrudFields } from './useSmartCrudFields';
import type { DataRecord } from '@nubit/core';
import { useCoreTranslation } from '../../core/i18n/useCoreTranslation';

function CrudSkeleton() {
  return (
    <div className="p-6 space-y-4 animate-pulse">
      <div className="h-8 bg-gray-200 rounded w-1/3" />
      <div className="h-64 bg-gray-200 rounded" />
    </div>
  );
}

function CrudError({ message, onRetry }: { message: string; onRetry: () => void }) {
  const { t } = useCoreTranslation();

  return (
    <div className="p-6 space-y-3 text-sm text-secondary" role="alert">
      <p className="m-0 text-base font-semibold text-primary">{t('crudPage.schemaErrorTitle')}</p>
      <p className="m-0 max-w-2xl">{t('crudPage.schemaErrorMessage')}</p>
      <details className="max-w-2xl text-xs">
        <summary className="cursor-pointer text-secondary">{t('crudPage.schemaErrorDetails')}</summary>
        <pre className="mt-2 overflow-auto rounded border border-default p-2 whitespace-pre-wrap">{message}</pre>
      </details>
      <button onClick={onRetry} className="app-button app-button--secondary app-button--sm" type="button">
        {t('crudPage.schemaErrorRetry')}
      </button>
    </div>
  );
}

interface SmartCrudPageProps<T extends DataRecord = DataRecord> {
  resource: ResourceConfig<T>;
  /**
   * Legacy per-field overrides applied on top of auto-inferred Hydra fields.
   * Each entry must contain a `key` matching the field's `name`, plus any
   * `Field` properties to override (e.g. `{ key: 'name', label: 'Custom' }`).
   * Ignored when the canonical `resource.fieldContract` is present.
   * Has no effect when `resource.fields` is a non-empty manual array.
   */
  fieldOverrides?: FieldOverride[];
}

type RoutingFilters = Record<string, string>;

function formatRuntimeErrorMessage(error: Error): string {
  const issues = 'issues' in error && Array.isArray(error.issues) ? error.issues : [];
  return issues.length > 0 ? `${error.message} ${issues.join(' ')}` : error.message;
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
export function SmartCrudPage<T extends DataRecord = DataRecord>({
  resource,
  fieldOverrides,
}: SmartCrudPageProps<T>) {
  const queryClient = useQueryClient();

  const resolvedBaseResource = useMemo(() => resolveCrudResource(resource), [resource]);
  const events = resolvedBaseResource.events;

  // Determine whether the consumer supplied manual fields.
  const hasManualFields =
    !resource.fieldContract &&
    Array.isArray(resource.fields) &&
    (resource.fields as unknown[]).length > 0;

  const { fields, isLoading, error, supportedOperations } = useResourceSchema(
    resolvedBaseResource.apiUrl,
    hasManualFields ? (resource.fields as Field[]) : undefined,
    hasManualFields ? undefined : fieldOverrides,
    resource.fieldContract,
  );

  // Dev hint: log a defineResource() snippet once per resource URL when fields
  // were auto-inferred. No-op in production.
  const hintFiredRef = useRef(false);
  useEffect(() => {
    if (
      !resource.fieldContract &&
      !hasManualFields &&
      !isLoading &&
      !error &&
      fields.length > 0 &&
      !hintFiredRef.current
    ) {
      hintFiredRef.current = true;
      logDevHint(resource.apiUrl, fields, supportedOperations);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    resource.fieldContract,
    hasManualFields,
    isLoading,
    error,
    fields,
    resource.apiUrl,
    supportedOperations,
  ]);

  // Routing
  const routingState = useRouting(resource.routing);

  // Operation + form data state (unified)
  const { activeOperation, formData, handleFormDataChange } = useSmartCrudOperation(
    events,
    routingState,
  );

  // Roles
  const roles = useSmartCrudRoles();
  const stableRoles = useMemo(() => roles ?? [], [roles]);

  // Declarative rules pipeline
  const { processedFields, computedValues } = useSmartCrudFields(
    fields,
    activeOperation,
    formData,
    stableRoles,
  );

  // Real-time Mercure subscription
  useMercureSubscription(
    resource.apiUrl,
    () => {
      if (events.SUCCESS) {
        dispatch(events.SUCCESS);
      }
    },
    resolvedBaseResource.mercure !== false,
  );

  // Normalize apiUrl — ensure it always starts with '/'
  const normalizedApiUrl = resolvedBaseResource.apiUrl.startsWith('/')
    ? resolvedBaseResource.apiUrl
    : `/${resolvedBaseResource.apiUrl}`;

  const resolvedResource = useMemo(
    () =>
      ({
        ...resolvedBaseResource,
        ...(hasManualFields ? {} : { fields }),
        apiUrl: normalizedApiUrl,
        fields: processedFields,
        _supportedOperations: supportedOperations,
      }) as ResourceConfig<T>,
    [
      fields,
      hasManualFields,
      normalizedApiUrl,
      processedFields,
      resolvedBaseResource,
      supportedOperations,
    ],
  );

  // --- Render ---

  if (!hasManualFields && isLoading) {
    return <CrudSkeleton />;
  }

  if (!hasManualFields && error) {
    return (
      <CrudError
        message={formatRuntimeErrorMessage(error)}
        onRetry={() => queryClient.invalidateQueries({ queryKey: API_DOC_QUERY_KEY })}
      />
    );
  }

  return (
    <CrudPage
      resource={resolvedResource}
      onFormDataChange={handleFormDataChange}
      computedValues={computedValues}
      initialRecordId={routingState.initialRecordId}
      initialIsNew={routingState.initialIsNew}
      initialFilters={routingState.initialFilters}
      onFiltersChange={routingState.syncFilters as (filters: RoutingFilters) => void}
    />
  );
}
