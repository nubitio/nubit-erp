import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';

export function getDxEditorInputAttr(field: Field, isEditable: boolean): DataRecord {
  return {
    'aria-label': field.label,
    ...(!isEditable ? { class: 'form-editor-input' } : {}),
    ...(field.editorOptions?.inputAttr ?? {}),
  };
}

export function getDxRestEditorOptions(field: Field): DataRecord {
  const restEditorOptions = { ...(field.editorOptions ?? {}) };
  delete restEditorOptions.inputAttr;

  return restEditorOptions;
}
