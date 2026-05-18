import type { MutableRefObject } from 'react';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type { FormRef } from 'devextreme-react/form';
import type ArrayStore from 'devextreme/data/array_store';
import type DataSource from 'devextreme/data/data_source';

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

export function setDxDetailRows(
  gridRef: MutableRefObject<DataGridRef | null>,
  rows: FormDataRecord[],
): void {
  const store = gridRef.current?.instance().getDataSource().store() as ArrayStore;
  store.clear();
  rows.forEach((row) => {
    store.insert(row);
  });
  reloadDxDetailRows(gridRef);
}

export function saveDxDetailRows(gridRef: MutableRefObject<DataGridRef | null>): Promise<void> | void {
  return gridRef.current?.instance().saveEditData();
}

export function reloadDxDetailRows(gridRef: MutableRefObject<DataGridRef | null>): void {
  gridRef.current?.instance().getDataSource().reload();
}

export function setDxDetailLoading(
  gridRef: MutableRefObject<DataGridRef | null>,
  loading: boolean,
  loadingText: string,
  noDataText: string,
): void {
  const grid = gridRef.current?.instance();
  if (loading) {
    grid?.option('noDataText', '');
    grid?.beginCustomLoading(loadingText);
    return;
  }

  grid?.endCustomLoading();
  grid?.option('noDataText', noDataText);
}

export function resetDxDetailDataSource(dataSource: MutableRefObject<DataSource>): void {
  const store = dataSource.current?.store() as ArrayStore;
  store.clear();
  dataSource.current?.reload();
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

export function getDxEditorOption(
  formRef: MutableRefObject<FormRef | null>,
  field: string,
  optionName: string,
): unknown {
  return formRef.current?.instance().getEditor(field)?.option(optionName);
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

export function hasDxFormInstance(formRef: MutableRefObject<FormRef | null>): boolean {
  return Boolean(formRef.current?.instance());
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
