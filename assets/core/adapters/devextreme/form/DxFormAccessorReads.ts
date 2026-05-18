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

export function getDxNullableFormData(
  formRef: MutableRefObject<FormRef | null>,
): FormDataRecord | null {
  return (formRef.current?.instance().option('formData') ?? null) as FormDataRecord | null;
}

export function setDxFormData(
  formRef: MutableRefObject<FormRef | null>,
  formData: FormDataRecord,
): void {
  formRef.current?.instance().option('formData', formData);
}
