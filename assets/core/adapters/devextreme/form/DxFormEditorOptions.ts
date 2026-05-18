import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getDxFileEditorOptions } from './DxFormFileEditorOptions';
export { getDxEditorType } from './DxFormEditorType';

export const getDxSimpleEditorOptions = (
  options: FormSimpleEditorOptions,
  field: Field,
  formRef: MutableRefObject<FormRef | null>,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
): DataRecord => ({
  ...(options.select
    ? {
        dataSource: options.select.dataSource,
        valueExpr: options.select.valueExpr,
        displayExpr: options.select.displayExpr,
        buttons: ['dropDown'],
      }
    : {}),
  ...(options.numberFormat ? { format: options.numberFormat } : {}),
  ...(options.checkboxText ? { text: options.checkboxText } : {}),
  ...(options.date
    ? {
        type: options.date.type,
        displayFormat: options.date.displayFormat,
        defaultValue: options.date.defaultValue,
        min: options.date.min,
      }
    : {}),
  ...(options.password
    ? {
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
      }
    : {}),
  ...(options.file ? getDxFileEditorOptions(field, options.file, uploadedFiles) : {}),
  ...(options.html
    ? {
        height: options.html.height,
        value: options.html.value,
        toolbar: {
          items: options.html.toolbarItems,
        },
      }
    : {}),
  ...(options.switchHint ? { hint: options.switchHint } : {}),
});
