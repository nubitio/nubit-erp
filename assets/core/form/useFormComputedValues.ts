import { useEffect } from 'react';

import { syncComputedValuesIntoFormData } from './FormComputedValues';

type FormRecord = Record<string, unknown>;

export interface UseFormComputedValuesAccessors {
  getFormData: () => FormRecord | null;
  setFormData: (formData: FormRecord) => void;
}

export interface UseFormComputedValuesOptions {
  accessors: UseFormComputedValuesAccessors;
  computedFieldNames: string[];
  computedValues?: FormRecord;
  onFieldDataChanged?: (formData: FormRecord) => void;
}

export function useFormComputedValues({
  accessors,
  computedFieldNames,
  computedValues,
  onFieldDataChanged,
}: UseFormComputedValuesOptions): void {
  useEffect(() => {
    if (computedFieldNames.length === 0) {
      return;
    }

    const currentFormData = accessors.getFormData();
    if (currentFormData == null) {
      return;
    }

    const { changed, nextFormData } = syncComputedValuesIntoFormData(
      currentFormData,
      computedFieldNames,
      computedValues ?? {},
    );

    if (!changed) {
      return;
    }

    accessors.setFormData(nextFormData);
    onFieldDataChanged?.(nextFormData);
  }, [accessors, computedFieldNames, computedValues, onFieldDataChanged]);
}
