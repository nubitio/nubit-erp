import type { Field, SmartCrudFieldContract, DataRecord, FieldOverride } from '@nubit/crud';
import { resolveSmartCrudFields } from '@nubit/crud';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseHydraDoc, parseOpenApiDoc } from './openApiParser';
import { useSchemaContext } from './SchemaContext';

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
 * Normalizes an API URL by stripping any leading slash and query string so that
 * 'api/categories', '/api/categories', and '/api/categories?foo=bar' are treated as equal.
 */
function normalizeUrl(url: string): string {
  const base = url.split('?')[0];
  return base.startsWith('/') ? base.slice(1) : base;
}

export function useResourceSchema<T extends DataRecord = DataRecord>(
  apiUrl: string,
  manualFields?: Field[],
  overrides?: FieldOverride[],
  fieldContract?: SmartCrudFieldContract<T>,
): UseResourceSchemaResult {
  const { data, isLoading, error } = useSchemaContext();

  const resolveWithRuntimeErrors = (
    resolver: () => Field[],
    supportedOperations: string[] = [],
  ): UseResourceSchemaResult => {
    try {
      return {
        fields: resolver(),
        isLoading: false,
        error: undefined,
        supportedOperations,
      };
    } catch (runtimeError) {
      return {
        fields: [],
        isLoading: false,
        error: runtimeError instanceof Error ? runtimeError : new Error(String(runtimeError)),
        supportedOperations,
      };
    }
  };

  if (fieldContract?.source === 'manual') {
    return resolveWithRuntimeErrors(() => resolveSmartCrudFields({ contract: fieldContract }));
  }

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
    (className) => resourceMap[className],
  );

  return resolveWithRuntimeErrors(
    () =>
      resolveSmartCrudFields({
        baselineFields: inferred,
        contract: fieldContract,
        legacyOverrides: fieldContract ? undefined : overrides,
      }),
    resourceSchema.supportedOperations ?? [],
  );
}

export type { FieldOverride };
export { mergeOverrides } from '@nubit/crud';
