import { Field } from '@core/field/Field';
import { FormRef, SimpleItem } from 'devextreme-react/form';
import { MutableRefObject } from 'react';
import type { UploadedFile } from '@core/form/UploadedFile';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { buildValidationRules } from './DxFormValidationRules';
import { buildTemplate } from './DxFormFieldTemplate';
import { getDxFieldEditorOptions } from './DxFormFieldEditorOptions';
import { getDxFieldItemProps } from './DxFormFieldItemProps';

export const buildDxFormFieldItem = (
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

export const buildField = buildDxFormFieldItem;
