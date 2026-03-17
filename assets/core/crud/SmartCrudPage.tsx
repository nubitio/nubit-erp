import React from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { CrudPage } from './CrudPage';
import { useResourceSchema } from './hydra/useResourceSchema';
import type { ResourceConfig } from './ResourceConfig';
import type { Field } from '../field/Field';

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
 * SmartCrudPage — wraps CrudPage with Hydra auto-discovery.
 *
 * - If `resource.fields` is a non-empty array, it is used as-is (pass-through).
 * - If `resource.fields` is empty / absent, fields are inferred from the Hydra
 *   API spec at `/api/docs.json` via `useResourceSchema`.
 * - While the schema loads: renders a skeleton loader.
 * - If the schema fetch errors: renders a user-visible error with a retry button.
 */
export function SmartCrudPage<T extends Record<string, unknown> = Record<string, unknown>>({
  resource,
}: SmartCrudPageProps<T>) {
  const queryClient = useQueryClient();

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

  const resolvedResource: ResourceConfig<T> = hasManualFields ? resource : { ...resource, fields };

  return <CrudPage resource={resolvedResource} />;
}
