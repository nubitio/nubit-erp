import { MutableRefObject } from 'react';
import { FormRef } from 'devextreme-react/form';
import { DataGridRef } from 'devextreme-react/data-grid';
import ArrayStore from 'devextreme/data/array_store';
import toast from '@shared/lib/SharedUtils';
import { Field } from '@core/field/Field';
import { normalizeFormData } from '@core/form/FormDataTransform';
import type { FormHandle } from '@core/form/FormHandle';
import type { FormDataRecord } from '@core/form/FormDataSnapshot';

export interface UseDxFormApiRefs {
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  isEdit: MutableRefObject<boolean>;
}

/**
 * Imperative API methods for the DevExtreme form adapter, exposed via `useImperativeHandle`.
 *
 * Extracted from the form component to reduce component size while keeping a stable
 * API surface exposed by the form adapter.
 */
export function useDxFormApi(refs: UseDxFormApiRefs, fields: Field[]): FormHandle {
  const { formRef, gridRef, isEdit } = refs;

  const getFormData = () => {
    return formRef.current?.instance().option('formData');
  };

  const getFieldValue = (field: string) => {
    return formRef.current?.instance().getEditor(field)?.option('value');
  };

  const setFieldValue = (field: string, value: unknown) => {
    // updateData writes into formData directly — critical for readonly/disabled
    // editors whose editor option() change is not propagated back into formData
    // by DevExtreme (e.g. discountPercent, grandTotal).
    formRef.current?.instance().updateData(field, value);
    formRef.current?.instance().getEditor(field)?.option('value', value);
  };

  const setReadonly = (field: string, value: boolean) => {
    formRef.current?.instance().getEditor(field)?.option('readOnly', value);
  };

  const setDisabled = (field: string, value: boolean) => {
    formRef.current?.instance().getEditor(field)?.option('disabled', value);
  };

  const getDetailData = () => {
    return gridRef.current?.instance().getDataSource().items();
  };

  const setValues = (data: FormDataRecord) => {
    const row = normalizeFormData(data, fields);
    formRef.current?.instance().option('formData', row);
  };

  const setDetailData = (data: FormDataRecord[]) => {
    const store = gridRef.current?.instance().getDataSource().store() as ArrayStore;
    store.clear();
    data.forEach((row) => {
      store.insert(row);
    });
    reloadDetailData();
  };

  const saveDetailData = () => {
    return gridRef.current?.instance().saveEditData();
  };

  const reloadDetailData = () => {
    gridRef.current?.instance().getDataSource().reload();
  };

  const setError = (field: string, message: string) => {
    if (field === 'items') {
      toast(message, 'error');
      return;
    }
    const editor = formRef.current?.instance().getEditor(field);
    editor?.option({
      isValid: false,
      validationError: { message },
    });
  };

  const showField = (field: string) => {
    formRef.current?.instance().itemOption(field, 'visible', true);
  };

  const hideField = (field: string) => {
    formRef.current?.instance().itemOption(field, 'visible', false);
  };

  const enableValidation = (field: string) => {
    formRef.current?.instance().getEditor(field)?.option('isValid', true);
  };

  const disableValidation = (field: string) => {
    formRef.current?.instance().getEditor(field)?.option('isValid', false);
  };

  const validate = () => {
    return formRef.current?.instance().validate()?.isValid ?? true;
  };

  const setIsEdit = (value: boolean) => {
    isEdit.current = value;
  };

  return {
    getFormData,
    getFieldValue,
    setFieldValue,
    getDetailData,
    setDetailData,
    saveDetailData,
    reloadDetailData,
    setValues,
    setReadonly,
    setDisabled,
    validate,
    setError,
    showField,
    hideField,
    enableValidation,
    disableValidation,
    setIsEdit,
  };
}
