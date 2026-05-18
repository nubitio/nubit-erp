import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';

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
            const editor = formRef.current?.instance().getEditor(field.name);
            editor?.option('mode', editor.option('mode') === 'text' ? 'password' : 'text');
            e.component.option(
              'icon',
              editor?.option('mode') === 'text' ? 'ph ph-eye-slash' : 'ph ph-eye',
            );
          },
        },
      },
    ],
  };
}
