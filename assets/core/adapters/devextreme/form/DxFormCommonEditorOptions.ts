import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import { dispatch } from '@core/event/EventHook';
import { FORM_EVENTS } from '@core/form/FormEvents';
import type { FormOnChangeFn } from '@core/field/FieldCallbacks';
import type { Field } from '@core/field/Field';
import { getDxCustomButtonsOptions } from './DxFormCustomButtonsOptions';
import { getDxEditorInputAttr, getDxRestEditorOptions } from './DxFormEditorAttributes';

export function getDxCommonEditorOptions(
  field: Field,
  isEditable: boolean,
  formRef: MutableRefObject<FormRef | null>,
  existingButtons: unknown[] = [],
): DataRecord {
  return {
    ...(!isEditable ? { elementAttr: { class: 'form-editor' } } : {}),
    maxLength: field.maxLength,
    readOnly: isEditable ? field.readonly : true,
    disabled: field.disabled,
    onFocusIn: field.onClick,
    onValueChanged: (e: { value: unknown }) => {
      dispatch(FORM_EVENTS.FIELD_CHANGED, { field: field.name, value: e.value });
      if (field.onChange) {
        (field.onChange as FormOnChangeFn)(e.value);
      }
    },
    onKeyPress: field.onKeyPress,
    onItemClick: field.onSelect,
    itemRender: field.itemFormatter,
    itemTemplate: field.itemFormatter ? field.name : 'item',
    stylingMode: 'outlined',
    ...getDxRestEditorOptions(field),
    inputAttr: getDxEditorInputAttr(field, isEditable),
    ...getDxCustomButtonsOptions(field, formRef, existingButtons),
  };
}
