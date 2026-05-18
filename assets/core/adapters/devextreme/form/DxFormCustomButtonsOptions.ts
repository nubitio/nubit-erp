import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';

export function getDxCustomButtonsOptions(
  field: Field,
  formRef: MutableRefObject<FormRef | null>,
  existingButtons: unknown[],
): DataRecord {
  if (!field.buttons || field.buttons.length === 0) {
    return {};
  }

  const customButtons = field.buttons.map((btn: DataRecord) => {
    const opts = btn.options as DataRecord | undefined;
    if (!opts?.onClick) return btn;

    const originalOnClick = opts.onClick as (...args: unknown[]) => void;
    return {
      ...btn,
      options: {
        ...opts,
        onClick: (...args: unknown[]) => originalOnClick(...args, { formRef }),
      },
    };
  });

  return {
    buttons: [...existingButtons, ...customButtons],
  };
}
