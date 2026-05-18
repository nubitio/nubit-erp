import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { FormDataRecord } from '@core/form/FormDataSnapshot';

export function getDxDetailRows(gridRef: MutableRefObject<DataGridRef | null>): FormDataRecord[] {
  return (gridRef.current?.instance().getDataSource().items() ?? []) as FormDataRecord[];
}

export function getDxFormData(formRef: MutableRefObject<FormRef | null>): FormDataRecord {
  return (formRef.current?.instance().option('formData') ?? {}) as FormDataRecord;
}
