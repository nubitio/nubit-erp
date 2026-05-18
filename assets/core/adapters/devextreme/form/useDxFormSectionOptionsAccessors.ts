import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormSectionOptionsAccessors } from '@core/form/FormSectionOptionsAccessors';

export function useDxFormSectionOptionsAccessors(
  formRef: MutableRefObject<FormRef | null>,
): UseFormSectionOptionsAccessors {
  return {
    setItemOption: (itemName, optionName, value) =>
      formRef.current?.instance().itemOption(itemName, optionName, value),
  };
}
