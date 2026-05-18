import { Field } from '@core/field/Field';
import { FormOnChangeFn } from '@core/field/FieldCallbacks';
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
import { dispatch } from '@core/event/EventHook';
import { FORM_EVENTS } from '@core/form/FormEvents';
import type { UploadedFile } from '@core/form/UploadedFile';
import { ValidationRule } from '@core/field/ValidationRule';
import { Template } from 'devextreme-react/core/template';
import type { DataRecord } from '@core/data/DataRecord';
import { getFormEditorKind } from '@core/form/FormEditorKind';
import { getFormSimpleEditorOptions } from '@core/form/FormSimpleEditorOptions';
import { getFormFieldPresentation } from '@core/form/FormFieldPresentation';
import { getFormRemoteOptions } from '@core/form/FormRemoteOptions';
import { getDxEditorType, getDxSimpleEditorOptions } from './DxFormEditorOptions';
import { getDxRemoteEditorOptions } from './DxFormRemoteEditorOptions';

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
  if (!isEditable) {
    editorOptions = {
      ...editorOptions,
      elementAttr: { class: 'form-editor' },
      // inputAttr for read-only mode is merged below together with aria-label
    };
  }
  // Build a merged inputAttr: aria-label from field.label as base,
  // read-only class (when !isEditable) applied next,
  // then explicit field.editorOptions.inputAttr values take priority (override).
  const mergedInputAttr = {
    'aria-label': field.label,
    ...(!isEditable ? { class: 'form-editor-input' } : {}),
    ...(field.editorOptions?.inputAttr ?? {}),
  };
  // Spread field.editorOptions but extract inputAttr to avoid it overwriting
  // the merged value computed above.
  const restEditorOptions = { ...(field.editorOptions ?? {}) };
  delete restEditorOptions.inputAttr;
  editorOptions = {
    ...editorOptions,
    maxLength: field.maxLength,
    readOnly: isEditable ? field.readonly : true,
    disabled: field.disabled,
    onFocusIn: field.onClick,
    onValueChanged: (e: any) => {
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
    ...restEditorOptions,
    inputAttr: mergedInputAttr,
  };

  const presentation = getFormFieldPresentation(field);

  if (presentation.useFloatingLabel) {
    editorOptions = {
      ...editorOptions,
      label: field.label,
      labelMode: 'floating',
    };
  }

  if (field.buttons && field.buttons.length > 0) {
    const enrichedButtons = field.buttons.map((btn: DataRecord) => {
      const opts = btn.options as DataRecord | undefined;
      if (!opts?.onClick) return btn;
      const originalOnClick = opts.onClick as (...args: unknown[]) => void;
      return {
        ...btn,
        options: {
          ...opts,
          onClick: (...args: unknown[]) => originalOnClick(...args, { formRef }),
        },
      };
    });
    editorOptions.buttons = [...(editorOptions.buttons ?? []), ...enrichedButtons];
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
