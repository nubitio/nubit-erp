import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormSubmitAccessors, UploadedFile } from '@core/form/useFormSubmit';

type FormRecord = Record<string, unknown>;

export interface UseDxFormSubmitAccessorsRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  isEdit: MutableRefObject<boolean>;
  uploadedFiles: MutableRefObject<UploadedFile[]>;
}

export function useDxFormSubmitAccessors({
  formRef,
  gridRef,
  isEdit,
  uploadedFiles,
}: UseDxFormSubmitAccessorsRefs): UseFormSubmitAccessors {
  return {
    getDetailRows: () =>
      (gridRef.current?.instance().getDataSource().items() ?? []) as FormRecord[],
    getFieldValue: (field) => formRef.current?.instance().getEditor(field)?.option('value'),
    getFormData: () => (formRef.current?.instance().option('formData') ?? {}) as FormRecord,
    getUploadedFiles: () => uploadedFiles.current,
    isEditMode: () => isEdit.current,
  };
}
