import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import { getDxEditorOption, setDxEditorOption } from './DxFormAccessorReads';

export function getDxPasswordEditorOptions(
  field: Field,
  formRef: MutableRefObject<FormRef | null>,
): DataRecord {
  return {
    mode: 'password',
    buttons: [
      {
        name: 'password',
        location: 'after',
        options: {
          icon: 'ph ph-eye ',
          stylingMode: 'text',
          onClick: (e: any) => {
            const mode = getDxEditorOption(formRef, field.name, 'mode') === 'text' ? 'password' : 'text';
            setDxEditorOption(formRef, field.name, 'mode', mode);
            e.component.option(
              'icon',
              mode === 'text' ? 'ph ph-eye-slash' : 'ph ph-eye',
            );
          },
        },
      },
    ],
  };
}
