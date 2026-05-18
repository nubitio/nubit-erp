import type { DataRecord } from '@core/data/DataRecord';
import type { Field } from '@core/field/Field';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getDxEditorType } from './DxFormEditorType';
import { getDxFieldPresentation } from './DxFormFieldPresentation';

export function getDxFieldItemProps(field: Field, editorOptions: DataRecord): DataRecord {
  const presentation = getDxFieldPresentation(field);

  return {
    dataField: field.name,
    editorType: getDxEditorType(getFormEditorKind(field)),
    label: presentation.label,
    isRequired: field.visible !== false && field.required,
    editorOptions,
    helpText: field.helpText,
    colSpan: field.col ?? 12,
    visible: field.visibleOnForm,
    render: field.contentRender,
    cssClass: presentation.cssClass,
  };
}
