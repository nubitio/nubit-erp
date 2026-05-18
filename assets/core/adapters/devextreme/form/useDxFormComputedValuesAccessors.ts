import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormComputedValuesAccessors } from '@core/form/FormComputedValuesAccessors';
import { getDxNullableFormData, setDxFormData } from './DxFormAccessorReads';

export function useDxFormComputedValuesAccessors(
  formRef: MutableRefObject<FormRef | null>,
): UseFormComputedValuesAccessors {
  return {
    getFormData: () => getDxNullableFormData(formRef),
    setFormData: (formData) => setDxFormData(formRef, formData),
  };
}
