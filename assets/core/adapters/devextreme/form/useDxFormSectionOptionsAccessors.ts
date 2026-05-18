import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormSectionOptionsAccessors } from '@core/form/FormSectionOptionsAccessors';
import { setDxItemOption } from './DxFormAccessorReads';

export function useDxFormSectionOptionsAccessors(
  formRef: MutableRefObject<FormRef | null>,
): UseFormSectionOptionsAccessors {
  return {
    setItemOption: (itemName, optionName, value) => setDxItemOption(formRef, itemName, optionName, value),
  };
}
