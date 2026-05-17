import { MutableRefObject, useEffect } from 'react';

import { syncComputedValuesIntoFormData } from './FormComputedValues';

type FormRecord = Record<string, unknown>;

export interface ComputedValuesFormRef {
  instance: () => {
    option: {
      (name: 'formData'): unknown;
      (name: 'formData', value: FormRecord): void;
    };
  };
}

export interface UseFormComputedValuesOptions {
  computedFieldNames: string[];
  computedValues?: FormRecord;
  formRef: MutableRefObject<ComputedValuesFormRef | null>;
  onFieldDataChanged?: (formData: FormRecord) => void;
}

export function useFormComputedValues({
  computedFieldNames,
  computedValues,
  formRef,
  onFieldDataChanged,
}: UseFormComputedValuesOptions): void {
  useEffect(() => {
    const instance = formRef.current?.instance();
    if (!instance || computedFieldNames.length === 0) {
      return;
    }

    const currentFormData = (instance.option('formData') ?? null) as FormRecord | null;
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

    instance.option('formData', nextFormData);
    onFieldDataChanged?.(nextFormData);
  }, [computedFieldNames, computedValues, formRef, onFieldDataChanged]);
}
