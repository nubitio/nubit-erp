import type { Field } from '../../field/Field';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseHydraDoc, parseOpenApiDoc } from './openApiParser';
import { useSchemaContext } from './SchemaContext';

export type FieldOverride = Partial<Field> & { key: string };

export interface UseResourceSchemaResult {
  fields: Field[];
  isLoading: boolean;
  error: Error | undefined;
  /**
   * HTTP methods supported by this resource class, derived from
   * `hydra:supportedOperation` in the Hydra API doc.
   * e.g. ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
   * Empty array when no operation info is available (Hydra not used, or
   * manual fields supplied — the caller should fall back to platform defaults).
   */
  supportedOperations: string[];
}

/**
 * Normalizes an API URL by stripping any leading slash so that
 * 'api/categories' and '/api/categories' are treated as equal.
 */
function normalizeUrl(url: string): string {
  return url.startsWith('/') ? url.slice(1) : url;
}

export function useResourceSchema(
  apiUrl: string,
  manualFields?: Field[],
  overrides?: FieldOverride[],
): UseResourceSchemaResult {
  const { data, isLoading, error } = useSchemaContext();

  if (manualFields && manualFields.length > 0) {
    return { fields: manualFields, isLoading: false, error: undefined, supportedOperations: [] };
  }

  if (!data) {
    return { fields: [], isLoading, error, supportedOperations: [] };
  }

  const resourceMap = data.format === 'hydra' ? parseHydraDoc(data.doc) : parseOpenApiDoc(data.doc);
  const normalizedInput = normalizeUrl(apiUrl);

  const resourceSchema = Object.values(resourceMap).find(
    (r) => normalizeUrl(r.apiUrl) === normalizedInput,
  );
  if (!resourceSchema) {
    return {
      fields: [],
      isLoading: false,
      error: new Error(`No schema found for ${apiUrl} in API doc`),
      supportedOperations: [],
    };
  }

  const inferred = mapHydraSchemaToFields(
    resourceSchema,
    (className) => resourceMap[className]?.apiUrl,
  );
  const merged = overrides ? mergeOverrides(inferred, overrides) : inferred;
  return {
    fields: merged,
    isLoading: false,
    error: undefined,
    supportedOperations: resourceSchema.supportedOperations ?? [],
  };
}

export function mergeOverrides(inferred: Field[], overrides: FieldOverride[]): Field[] {
  return inferred.map((field) => {
    // Match override by `key` discriminator (maps to field.name).
    const override = overrides.find((o) => o.key === field.name);
    if (!override) return field;
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { key: _discardedKey, ...overrideProps } = override;
    return { ...field, ...overrideProps };
  });
}
