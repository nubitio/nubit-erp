import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormComputedValuesAccessors } from '@core/form/FormComputedValuesAccessors';
import type { FormDataRecord } from '@core/form/FormDataSnapshot';

export function useDxFormComputedValuesAccessors(
  formRef: MutableRefObject<FormRef | null>,
): UseFormComputedValuesAccessors {
  return {
    getFormData: () => (formRef.current?.instance().option('formData') ?? null) as FormDataRecord | null,
    setFormData: (formData) => formRef.current?.instance().option('formData', formData),
  };
}
