import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { Field } from '@core/field/Field';
import type { FormFieldRenderer } from '@core/form/FormFieldRenderer';
import type { UploadedFile } from '@core/form/UploadedFile';

import { buildField } from './DxFormFieldItem';

export function useDxFormFieldRenderer(
  editable: boolean | undefined,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
  formRef: MutableRefObject<FormRef | null>,
): FormFieldRenderer {
  return (fields: Field[]) => {
    return fields.map((field, index) => {
      return buildField(field, index, editable ?? true, uploadedFiles, formRef);
    });
  };
}
