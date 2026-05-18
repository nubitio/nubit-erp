import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { UseFormValidationAccessors } from '@core/form/FormValidationAccessors';
import type { FormDataRecord } from '@core/form/FormDataSnapshot';

export interface UseDxFormValidationAccessorsRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
}

export function useDxFormValidationAccessors({
  formRef,
  gridRef,
}: UseDxFormValidationAccessorsRefs): UseFormValidationAccessors {
  return {
    getDetailRowCount: () => gridRef.current?.instance().totalCount() ?? 0,
    getDetailRows: () =>
      (gridRef.current?.instance().getDataSource().items() ?? []) as FormDataRecord[],
    hasPendingDetailEdits: () => gridRef.current?.instance().hasEditData() ?? false,
    validateForm: () => formRef.current?.instance().validate()?.isValid ?? true,
  };
}
