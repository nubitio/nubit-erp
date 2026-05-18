import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';

import type { FormDataRecord } from '@core/form/FormDataSnapshot';

export function getDxDetailRows(gridRef: MutableRefObject<DataGridRef | null>): FormDataRecord[] {
  return (gridRef.current?.instance().getDataSource().items() ?? []) as FormDataRecord[];
}

export function getDxDetailRowCount(gridRef: MutableRefObject<DataGridRef | null>): number {
  return gridRef.current?.instance().totalCount() ?? 0;
}

export function hasDxPendingDetailEdits(gridRef: MutableRefObject<DataGridRef | null>): boolean {
  return gridRef.current?.instance().hasEditData() ?? false;
}

export function getDxFormData(formRef: MutableRefObject<FormRef | null>): FormDataRecord {
  return (formRef.current?.instance().option('formData') ?? {}) as FormDataRecord;
}

export function getDxFieldValue(
  formRef: MutableRefObject<FormRef | null>,
  field: string,
): unknown {
  return formRef.current?.instance().getEditor(field)?.option('value');
}

export function setDxFieldValue(
  formRef: MutableRefObject<FormRef | null>,
  field: string,
  value: unknown,
): void {
  const form = formRef.current?.instance();
  // updateData writes into formData directly — critical for readonly/disabled
  // editors whose editor option() change is not propagated back into formData
  // by DevExtreme (e.g. discountPercent, grandTotal).
  form?.updateData(field, value);
  form?.getEditor(field)?.option('value', value);
}

export function setDxEditorOption(
  formRef: MutableRefObject<FormRef | null>,
  field: string,
  optionName: string,
  value: unknown,
): void {
  formRef.current?.instance().getEditor(field)?.option(optionName, value);
}

export function setDxEditorOptions(
  formRef: MutableRefObject<FormRef | null>,
  field: string,
  options: Record<string, unknown>,
): void {
  formRef.current?.instance().getEditor(field)?.option(options);
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

export function validateDxForm(formRef: MutableRefObject<FormRef | null>): boolean {
  return formRef.current?.instance().validate()?.isValid ?? true;
}

export function setDxItemOption(
  formRef: MutableRefObject<FormRef | null>,
  itemName: string,
  optionName: string,
  value: unknown,
): void {
  formRef.current?.instance().itemOption(itemName, optionName, value);
}
