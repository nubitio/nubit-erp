import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import type { FormEditorKind } from '@core/form/FormEditorKind';
import type { FormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getIRI } from '@shared/lib/SharedUtils';

// DevExtreme exposes editorType as a vendor-specific literal union; keep that
// typing local to this adapter while the app works with FormEditorKind.
export const getDxEditorType = (editorKind: FormEditorKind | null): any => {
  switch (editorKind) {
    case 'text':
      return 'dxTextBox';
    case 'number':
      return 'dxNumberBox';
    case 'date':
      return 'dxDateBox';
    case 'select':
      return 'dxSelectBox';
    case 'checkbox':
      return 'dxCheckBox';
    case 'radio':
      return 'dxRadioGroup';
    case 'textarea':
      return 'dxTextArea';
    case 'file':
      return 'dxFileUploader';
    case 'switch':
      return 'dxSwitch';
    case 'tags':
      return 'dxTagBox';
    case 'html':
      return 'dxHtmlEditor';
    case null:
      return undefined;
  }
};

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
  ...(options.file
    ? {
        accept: options.file.accept,
        ...(options.file.uploadUrl === undefined
          ? { uploadMode: 'useForm' }
          : {
              uploadUrl: options.file.uploadUrl,
              name: 'file',
              onBeforeSend: () => {},
              onUploaded: (e: any) => {
                const response = JSON.parse(e.request.response);
                uploadedFiles.current.push({
                  name: field.name,
                  iri: getIRI(options.file?.uploadUrl ?? '', response.id.toString()),
                });
              },
            }),
        selectButtonText: options.file.selectButtonText,
      }
    : {}),
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
