import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormValidationAccessors } from '@core/form/FormValidationAccessors';
import {
  getDxDetailRowCount,
  getDxDetailRows,
  hasDxPendingDetailEdits,
  validateDxForm,
} from './DxFormAccessorReads';

export interface UseDxFormValidationAccessorsRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
}

export function useDxFormValidationAccessors({
  formRef,
  gridRef,
}: UseDxFormValidationAccessorsRefs): UseFormValidationAccessors {
  return {
    getDetailRowCount: () => getDxDetailRowCount(gridRef),
    getDetailRows: () => getDxDetailRows(gridRef),
    hasPendingDetailEdits: () => hasDxPendingDetailEdits(gridRef),
    validateForm: () => validateDxForm(formRef),
  };
}
