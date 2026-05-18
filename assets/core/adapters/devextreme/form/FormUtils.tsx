import { Field } from '@core/field/Field';
import {
  CompareRule,
  CustomRule,
  EmailRule,
  FormRef,
  PatternRule,
  RangeRule,
  RequiredRule,
  SimpleItem,
  StringLengthRule,
} from 'devextreme-react/form';
import { MutableRefObject } from 'react';
import type { UploadedFile } from '@core/form/UploadedFile';
import { ValidationRule } from '@core/field/ValidationRule';
import { Template } from 'devextreme-react/core/template';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getFormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import { getFormFieldPresentation } from '@core/form/FormFieldPresentation';
import { getFormRemoteOptions } from '@core/form/FormRemoteOptions';
import { getDxEditorType, getDxSimpleEditorOptions } from './DxFormEditorOptions';
import { getDxRemoteEditorOptions } from './DxFormRemoteEditorOptions';
import { getDxCommonEditorOptions } from './DxFormCommonEditorOptions';

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

const buildValidationRules = (field: Field) => {
  // Skip validation rules for hidden fields to avoid blocking form submission
  // when a field is conditionally hidden via visibleWhen or visible(false).
  if (field.visibleOnForm === false || field.visible === false) {
    return [];
  }

  let validationRules: ValidationRule[] = [];
  if (field.required) {
    validationRules.push({
      type: 'required',
      options: {
        message: `${field.label} es requerido`,
      },
    });
  }
  validationRules = validationRules.concat(field.validators ?? []);
  return validationRules.map((rule, index) => {
    switch (rule.type) {
      case 'required':
        return <RequiredRule key={index} />;
      case 'pattern':
        return (
          <PatternRule key={index} pattern={rule.options.pattern} message={rule.options.message} />
        );
      case 'email':
        return <EmailRule key={index} message={rule.options.message} />;
      case 'compare':
        return (
          <CompareRule
            key={index}
            comparisonTarget={rule.options.comparisonTarget}
            comparisonType={rule.options.comparisonType}
            message={rule.options.message}
          />
        );
      case 'stringLength':
        return (
          <StringLengthRule
            key={index}
            min={rule.options.min}
            max={rule.options.max}
            message={rule.options.message}
          />
        );
      case 'range':
        return (
          <RangeRule
            key={index}
            min={rule.options.min}
            max={rule.options.max}
            message={rule.options.message}
          />
        );
      case 'custom':
        return (
          <CustomRule
            key={index}
            validationCallback={rule.options.validationCallback}
            message={rule.options.message}
          />
        );
      default:
        return null;
    }
  });
};

// Exported for testing only — not part of the public API
export const _getEditorType = (field: Field) => getDxEditorType(getFormEditorKind(field));
export const _buildValidationRules = buildValidationRules;
