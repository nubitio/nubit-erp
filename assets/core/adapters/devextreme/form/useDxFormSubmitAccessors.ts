import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormSubmitAccessors } from '@core/form/FormSubmitAccessors';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getDxDetailRows, getDxFieldValue, getDxFormData } from './DxFormAccessorReads';

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
    getDetailRows: () => getDxDetailRows(gridRef),
    getFieldValue: (field) => getDxFieldValue(formRef, field),
    getFormData: () => getDxFormData(formRef),
    getUploadedFiles: () => uploadedFiles.current,
    isEditMode: () => isEdit.current,
  };
}
