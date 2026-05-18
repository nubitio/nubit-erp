import { Field } from '@core/field/Field';
import { FormRef, SimpleItem } from 'devextreme-react/form';
import { MutableRefObject } from 'react';
import type { UploadedFile } from '@core/form/UploadedFile';
import { Template } from 'devextreme-react/core/template';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getFormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import { getFormFieldPresentation } from '@core/form/FormFieldPresentation';
import { getFormRemoteOptions } from '@core/form/FormRemoteOptions';
import { getDxEditorType, getDxSimpleEditorOptions } from './DxFormEditorOptions';
import { getDxRemoteEditorOptions } from './DxFormRemoteEditorOptions';
import { getDxCommonEditorOptions } from './DxFormCommonEditorOptions';
import { buildValidationRules } from './DxFormValidationRules';

export const buildField = (
  field: Field,
  key: number | string,
  isEditable: boolean = true,
  uploadedFiles: MutableRefObject<UploadedFile[]> = { current: [] },
  formRef: MutableRefObject<FormRef | null> = { current: null },
) => {
  if (getFormEditorKind(field) === null) return null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- DevExtreme editor options bag varies by widget type
  let editorOptions: Record<string, any> = getDxRemoteEditorOptions(
    field,
    getFormRemoteOptions(field),
    formRef,
  );
  editorOptions = {
    ...editorOptions,
    ...getDxSimpleEditorOptions(getFormSimpleEditorOptions(field), field, formRef, uploadedFiles),
  };
  editorOptions = {
    ...editorOptions,
    ...getDxCommonEditorOptions(field, isEditable, formRef, editorOptions.buttons),
  };

  const presentation = getFormFieldPresentation(field);

  if (presentation.useFloatingLabel) {
    editorOptions = {
      ...editorOptions,
      label: field.label,
      labelMode: 'floating',
    };
  }

  const className = field.hidden ? 'dx-hidden' : '';

  return (
    <SimpleItem
      key={key}
      dataField={field.name}
      editorType={getDxEditorType(getFormEditorKind(field))}
      label={{ text: field.label, visible: !presentation.useFloatingLabel && !presentation.hideLabel }}
      isRequired={field.visible !== false && field.required}
      editorOptions={editorOptions}
      helpText={field.helpText}
      colSpan={field.col ?? 12}
      visible={field.visibleOnForm}
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      render={field.contentRender as any}
      cssClass={className}
    >
      {buildTemplate(field)}
      {buildValidationRules(field)}
    </SimpleItem>
  );
};

const buildTemplate = (field: Field) => {
  if (field.itemFormatter) {
    return <Template name={field.name} render={field.itemFormatter} />;
  }
};

// Exported for testing only — not part of the public API
export const _getEditorType = (field: Field) => getDxEditorType(getFormEditorKind(field));
export const _buildValidationRules = buildValidationRules;
