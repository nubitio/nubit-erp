import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormComputedValuesAccessors } from '@core/form/useFormComputedValues';

type FormRecord = Record<string, unknown>;

export function useDxFormComputedValuesAccessors(
  formRef: MutableRefObject<FormRef | null>,
): UseFormComputedValuesAccessors {
  return {
    getFormData: () => (formRef.current?.instance().option('formData') ?? null) as FormRecord | null,
    setFormData: (formData) => formRef.current?.instance().option('formData', formData),
  };
}
