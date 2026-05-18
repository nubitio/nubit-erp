import type { MutableRefObject } from 'react';
import type { FormRef } from 'devextreme-react/form';

import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import { getDxCustomButtonsOptions } from './DxFormCustomButtonsOptions';
import { getDxEditorInputAttr, getDxRestEditorOptions } from './DxFormEditorAttributes';
import { getDxEditorValueChangedHandler } from './DxFormEditorEvents';

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
    onValueChanged: getDxEditorValueChangedHandler(field),
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
