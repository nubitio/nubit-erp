import type { Field } from '../../field/Field';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
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
  const { spec, isLoading, error } = useHydraMetadata();

  // If manual fields provided, use them as-is (no Hydra needed)
  if (manualFields && manualFields.length > 0) {
    return { fields: manualFields, isLoading: false, error: undefined };
  }

  if (!spec) {
    return { fields: [], isLoading, error };
  }

  const resourceSchema = spec.resources[apiUrl];
  if (!resourceSchema) {
    return {
      fields: [],
      isLoading: false,
      error: new Error(`No schema found for ${apiUrl} in API spec`),
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
