import type { Field } from '../../field/Field';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseHydraDoc } from './openApiParser';
import { useHydraMetadata } from './useHydraMetadata';

export type FieldOverride = Partial<Field> & { name: string };

export interface UseResourceSchemaResult {
  fields: Field[];
  isLoading: boolean;
  error: Error | undefined;
}

export function useResourceSchema(
  apiUrl: string,
  manualFields?: Field[],
  overrides?: FieldOverride[],
): UseResourceSchemaResult {
  const { doc, isLoading, error } = useHydraMetadata();

  // If manual fields provided, use them as-is (no Hydra needed)
  if (manualFields && manualFields.length > 0) {
    return { fields: manualFields, isLoading: false, error: undefined };
  }

  if (!doc) {
    return { fields: [], isLoading, error };
  }

  // Parse the full Hydra doc into a resource map, then look up by apiUrl
  const resourceMap = parseHydraDoc(doc);

  // Find the resource schema whose apiUrl matches
  const resourceSchema = Object.values(resourceMap).find((r) => r.apiUrl === apiUrl);
  if (!resourceSchema) {
    return {
      fields: [],
      isLoading: false,
      error: new Error(`No schema found for ${apiUrl} in Hydra API doc`),
    };
  }

  const inferred = mapHydraSchemaToFields(resourceSchema);
  const merged = overrides ? mergeOverrides(inferred, overrides) : inferred;
  return { fields: merged, isLoading: false, error: undefined };
}

export function mergeOverrides(inferred: Field[], overrides: FieldOverride[]): Field[] {
  return inferred.map((field) => {
    const override = overrides.find((o) => o.name === field.name);
    return override ? { ...field, ...override } : field;
  });
}
