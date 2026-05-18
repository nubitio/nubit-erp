import { MutableRefObject } from 'react';
import { FormRef } from 'devextreme-react/form';
import { DataGridRef } from 'devextreme-react/data-grid';
import toast from '@shared/lib/SharedUtils';
import { Field } from '@core/field/Field';
import { normalizeFormData } from '@core/form/FormDataTransform';
import type { FormHandle } from '@core/form/FormHandle';
import type { FormDataRecord } from '@core/form/FormDataSnapshot';
import {
  getDxDetailRows,
  getDxFieldValue,
  getDxFormData,
  reloadDxDetailRows,
  saveDxDetailRows,
  setDxEditorOption,
  setDxEditorOptions,
  setDxFieldValue,
  setDxDetailRows,
  setDxFormData,
  setDxItemOption,
  validateDxForm,
} from './DxFormAccessorReads';

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
    return getDxFormData(formRef);
  };

  const getFieldValue = (field: string) => {
    return getDxFieldValue(formRef, field);
  };

  const setFieldValue = (field: string, value: unknown) => {
    setDxFieldValue(formRef, field, value);
  };

  const setReadonly = (field: string, value: boolean) => {
    setDxEditorOption(formRef, field, 'readOnly', value);
  };

  const setDisabled = (field: string, value: boolean) => {
    setDxEditorOption(formRef, field, 'disabled', value);
  };

  const getDetailData = () => {
    return getDxDetailRows(gridRef);
  };

  const setValues = (data: FormDataRecord) => {
    const row = normalizeFormData(data, fields);
    setDxFormData(formRef, row);
  };

  const setDetailData = (data: FormDataRecord[]) => {
    setDxDetailRows(gridRef, data);
  };

  const saveDetailData = () => {
    return saveDxDetailRows(gridRef);
  };

  const reloadDetailData = () => {
    reloadDxDetailRows(gridRef);
  };

  const setError = (field: string, message: string) => {
    if (field === 'items') {
      toast(message, 'error');
      return;
    }
    setDxEditorOptions(formRef, field, {
      isValid: false,
      validationError: { message },
    });
  };

  const showField = (field: string) => {
    setDxItemOption(formRef, field, 'visible', true);
  };

  const hideField = (field: string) => {
    setDxItemOption(formRef, field, 'visible', false);
  };

  const enableValidation = (field: string) => {
    setDxEditorOption(formRef, field, 'isValid', true);
  };

  const disableValidation = (field: string) => {
    setDxEditorOption(formRef, field, 'isValid', false);
  };

  const validate = () => {
    return validateDxForm(formRef);
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
