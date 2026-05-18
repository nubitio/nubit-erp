import { Field } from '@core/field/Field';
import { FormRef, SimpleItem } from 'devextreme-react/form';
import { MutableRefObject } from 'react';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getDxEditorType } from './DxFormEditorType';
import { buildValidationRules } from './DxFormValidationRules';
import { buildTemplate } from './DxFormFieldTemplate';
import { getDxFieldEditorOptions } from './DxFormFieldEditorOptions';
import { getDxFieldItemProps } from './DxFormFieldItemProps';

export const buildField = (
  field: Field,
  key: number | string,
  isEditable: boolean = true,
  uploadedFiles: MutableRefObject<UploadedFile[]> = { current: [] },
  formRef: MutableRefObject<FormRef | null> = { current: null },
) => {
  if (getFormEditorKind(field) === null) return null;
  const editorOptions = getDxFieldEditorOptions(field, isEditable, uploadedFiles, formRef);
  const itemProps = getDxFieldItemProps(field, editorOptions);

  return (
    <SimpleItem
      key={key}
      {...itemProps}
    >
      {buildTemplate(field)}
      {buildValidationRules(field)}
    </SimpleItem>
  );
};

// Exported for testing only — not part of the public API
export const _getEditorType = (field: Field) => getDxEditorType(getFormEditorKind(field));
export const _buildValidationRules = buildValidationRules;
