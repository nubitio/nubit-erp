import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { Field } from '@core/field/Field';
import type { UploadedFile } from '@core/form/useFormSubmit';

import { buildField } from './FormUtils';

export function useDxFormFieldRenderer(
  editable: boolean | undefined,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
  formRef: MutableRefObject<FormRef | null>,
) {
  return (fields: Field[]) => {
    return fields.map((field, index) => {
      return buildField(field, index, editable ?? true, uploadedFiles, formRef);
    });
  };
}
