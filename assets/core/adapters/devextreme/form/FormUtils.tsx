import { Field } from '@core/field/Field';
import { FormOnChangeFn } from '@core/field/FieldCallbacks';
import { dxUtils } from '@core/adapters/devextreme/data/DxUtils';
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
import { getIRI } from '@shared/lib/SharedUtils';
import { Template } from 'devextreme-react/core/template';
import type { DataRecord } from '@core/data/DataRecord';
import { FormEditorKind, getFormEditorKind } from '@core/form/FormEditorKind';
import {
  FormSimpleEditorOptions,
  getFormSimpleEditorOptions,
} from '@core/form/FormSimpleEditorOptions';
import { getFormFieldPresentation } from '@core/form/FormFieldPresentation';
import { getFormRemoteOptions } from '@core/form/FormRemoteOptions';

export const buildField = (
  field: Field,
  key: number | string,
  isEditable: boolean = true,
  uploadedFiles: MutableRefObject<UploadedFile[]> = { current: [] },
  formRef: MutableRefObject<FormRef | null> = { current: null },
) => {
  if (getFormEditorKind(field) === null) return null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- DevExtreme editor options bag varies by widget type
  let editorOptions: Record<string, any> = {};
  const remoteOptions = getFormRemoteOptions(field);
  if (remoteOptions) {
    // Create a store instance so we can query a small page to decide auto-selection
    const store = dxUtils.getDataStore(
      remoteOptions.url,
      remoteOptions.valueField,
      remoteOptions.byKeyUrl,
      remoteOptions.filters,
      [],
      remoteOptions.loadOptions,
      remoteOptions.iriMode,
    );

    editorOptions = {
      dataSource: {
        store: store,
        paginate: true,
        pageSize: 10,
      },
      dropDownOptions: {
        container: 'body',
        wrapperAttr: { class: 'crud-dialog-editor-overlay' },
      },
      valueExpr: remoteOptions.valueField,
      displayExpr: remoteOptions.textField,
      searchEnabled: remoteOptions.searchEnabled,
      searchExpr: remoteOptions.searchExpr,
      wrapItemText: true,
      selectionMode: remoteOptions.multiple ? 'multiple' : 'single',
      buttons: ['clear', 'dropDown'],
    };

    // Auto-select logic: only when the field opts in, and no explicit defaultValue has been provided
    try {
      if (remoteOptions.autoSelectIfSingle && remoteOptions.defaultValue === undefined) {
        // Case: local data already present on the field (mode !== 'remote')
        if (field.mode !== 'remote' && remoteOptions.localData.length === 1) {
          const single = remoteOptions.localData[0];
          const v = remoteOptions.multiple
            ? [single[remoteOptions.valueField]]
            : single[remoteOptions.valueField];
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
          field.defaultValue = v as any;
          formRef.current?.instance()?.getEditor(field.name)?.option('value', v);
        } else if (remoteOptions.url) {
          // For remote stores, load up to 2 items (so we can detect "exactly 1" without fetching all)
          // Respect any filters embedded in the store.
          // The CustomStore.load may return either an array or an object with .data
          // We deliberately request a small page: skip=0, take=2
          (store as any)
            .load({ skip: 0, take: 2 })
            .then((result: any) => {
              const items = result?.data ?? result;
              if (Array.isArray(items) && items.length === 1) {
                const single = items[0];
                const v = remoteOptions.multiple
                  ? [single[remoteOptions.valueField]]
                  : single[remoteOptions.valueField];
                field.defaultValue = v;
                // If the form is already mounted, set the editor value immediately
                formRef.current?.instance()?.getEditor(field.name)?.option('value', v);
              }
            })
            .catch((err: unknown) => {
              console.warn(`[DevExtremeForm] auto-select failed for "${field.name}":`, err);
            });
        }
      }
    } catch (err) {
      console.warn(`[DevExtremeForm] auto-select setup failed for "${field.name}":`, err);
    }
  }
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

// DevExtreme exposes editorType as a vendor-specific literal union; keep that
// typing local to this adapter while the app works with FormEditorKind.
const getDxEditorType = (editorKind: FormEditorKind | null): any => {
  switch (editorKind) {
    case 'text':
      return 'dxTextBox';
    case 'number':
      return 'dxNumberBox';
    case 'date':
      return 'dxDateBox';
    case 'select':
      return 'dxSelectBox';
    case 'checkbox':
      return 'dxCheckBox';
    case 'radio':
      return 'dxRadioGroup';
    case 'textarea':
      return 'dxTextArea';
    case 'file':
      return 'dxFileUploader';
    case 'switch':
      return 'dxSwitch';
    case 'tags':
      return 'dxTagBox';
    case 'html':
      return 'dxHtmlEditor';
    case null:
      return undefined;
  }
};

const getDxSimpleEditorOptions = (
  options: FormSimpleEditorOptions,
  field: Field,
  formRef: MutableRefObject<FormRef | null>,
  uploadedFiles: MutableRefObject<UploadedFile[]>,
): DataRecord => ({
  ...(options.select
    ? {
        dataSource: options.select.dataSource,
        valueExpr: options.select.valueExpr,
        displayExpr: options.select.displayExpr,
        buttons: ['dropDown'],
      }
    : {}),
  ...(options.numberFormat ? { format: options.numberFormat } : {}),
  ...(options.checkboxText ? { text: options.checkboxText } : {}),
  ...(options.date
    ? {
        type: options.date.type,
        displayFormat: options.date.displayFormat,
        defaultValue: options.date.defaultValue,
        min: options.date.min,
      }
    : {}),
  ...(options.password
    ? {
        mode: 'password',
        buttons: [
          {
            name: 'password',
            location: 'after',
            options: {
              icon: 'ph ph-eye ',
              stylingMode: 'text',
              onClick: (e: any) => {
                const editor = formRef.current?.instance().getEditor(field.name);
                editor?.option('mode', editor.option('mode') === 'text' ? 'password' : 'text');
                e.component.option(
                  'icon',
                  editor?.option('mode') === 'text' ? 'ph ph-eye-slash' : 'ph ph-eye',
                );
              },
            },
          },
        ],
      }
    : {}),
  ...(options.file
    ? {
        accept: options.file.accept,
        ...(options.file.uploadUrl === undefined
          ? { uploadMode: 'useForm' }
          : {
              uploadUrl: options.file.uploadUrl,
              name: 'file',
              onBeforeSend: () => {},
              onUploaded: (e: any) => {
                const response = JSON.parse(e.request.response);
                uploadedFiles.current.push({
                  name: field.name,
                  iri: getIRI(options.file?.uploadUrl ?? '', response.id.toString()),
                });
              },
            }),
        selectButtonText: options.file.selectButtonText,
      }
    : {}),
  ...(options.html
    ? {
        height: options.html.height,
        value: options.html.value,
        toolbar: {
          items: options.html.toolbarItems,
        },
      }
    : {}),
  ...(options.switchHint ? { hint: options.switchHint } : {}),
});

// Exported for testing only — not part of the public API
export const _getEditorType = (field: Field) => getDxEditorType(getFormEditorKind(field));
export const _buildValidationRules = buildValidationRules;
