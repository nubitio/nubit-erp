import { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { dxUtils } from '../common/DxUtils';
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
import { dispatch } from '../event/EventHook';
import { FORM_EVENTS, UploadedFile } from './DxForm';
import { ValidationRule } from '../field/ValidationRule';
import { getIRI } from '../utils/SharedUtils';

export const buildField = (
  field: Field,
  key: any,
  isEditable: boolean = true,
  uploadedFiles: MutableRefObject<UploadedFile[]> = { current: [] },
  formRef: MutableRefObject<FormRef | null> = { current: null },
) => {
  if (field.type === FieldType.NONE) return null;
  let editorOptions: any = {};
  if (field.type === FieldType.ENTITY || field.type === FieldType.TAGS) {
    // Create a store instance so we can query a small page to decide auto-selection
    const store = dxUtils.getDataStore(
      field.url ?? '',
      field.valueField,
      field.byKeyUrl,
      field.filters,
      [],
      field.loadOptions,
    );

    editorOptions = {
      dataSource: {
        store: store,
        paginate: true,
        pageSize: 10,
      },
      valueExpr: field.valueField,
      displayExpr: field.textField,
      searchEnabled: field.searchEnabled,
      searchExpr: field.searchExpr,
      wrapItemText: true,
      selectionMode: field.multiple ? 'multiple' : 'single',
      buttons: ['clear', 'dropDown'],
    };

    // Auto-select logic: only when the field opts in, and no explicit defaultValue has been provided
    try {
      if (field.autoSelectIfSingle === true && field.defaultValue === undefined) {
        // Case: local data already present on the field (mode !== 'remote')
        if (field.mode !== 'remote' && Array.isArray(field.data) && field.data.length === 1) {
          const single = field.data[0];
          const v = field.multiple ? [single[field.valueField]] : single[field.valueField];
          field.defaultValue = v;
          formRef.current?.instance()?.getEditor(field.name)?.option('value', v);
        } else if (field.url) {
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
                const v = field.multiple ? [single[field.valueField]] : single[field.valueField];
                field.defaultValue = v;
                // If the form is already mounted, set the editor value immediately
                formRef.current?.instance()?.getEditor(field.name)?.option('value', v);
              }
            })
            .catch((err: any) => {
              // Non-blocking: just log for debugging
              console.debug('auto-select single entity failed for', field.name, err);
            });
        }
      }
    } catch (e) {
      console.debug('auto-select logic error for field', field.name, e);
    }
  }
  if (field.type === FieldType.ENUM || field.type === FieldType.SELECT) {
    editorOptions = {
      dataSource: field.data,
      valueExpr: 'value',
      displayExpr: 'text',
      buttons: ['dropDown'],
    };
  }
  if (field.type === FieldType.FILE) {
    if (field.url === undefined) {
      editorOptions = {
        accept: field.accept,
        uploadMode: 'useForm',
      };
    } else {
      editorOptions = {
        accept: field.accept,
        uploadUrl: field.url,
        name: 'file',
        onBeforeSend: (e: any) => {
        },
        onUploaded: (e: any) => {
          const response = JSON.parse(e.request.response);
          uploadedFiles.current.push({
            name: field.name,
            iri: getIRI(field.url ?? '', response.id.toString()),
          });
          console.log(uploadedFiles.current);
        },
      };
    }
    editorOptions = {
      ...editorOptions,
      selectButtonText: 'Seleccionar ' + field.label.toLowerCase(),
    };
  }
  if (field.type === FieldType.DATE) {
    editorOptions = {
      type: 'date',
      displayFormat: field.format || 'dd/MM/yyyy',
      defaultValue: new Date(),
      min: field.data?.[0]?.min ?? undefined,
    };
  }
  if (field.type === FieldType.DATETIME) {
    editorOptions = {
      type: 'datetime',
      displayFormat: field.format || 'dd/MM/yyyy HH:mm',
      defaultValue: new Date(),
    };
  }
  if (field.type === FieldType.CURRENCY) {
    editorOptions = {
      format: {
        style: 'currency',
        currency: 'PEN',
      },
    };
  }
  if (field.type === FieldType.PASSWORD) {
    editorOptions = {
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
    };
  }
  if (field.type === FieldType.HTML) {
    editorOptions = {
      height: field.height,
      value: field.defaultValue,
      toolbar: {
        items: [
          'bold',
          'italic',
          'underline',
          'strike',
          'separator',
          'alignLeft',
          'alignCenter',
          'alignRight',
          'alignJustify',
          'separator',
          'orderedList',
          'bulletList',
        ],
      },
    };
  }
  if (!isEditable) {
    editorOptions = {
      ...editorOptions,
      elementAttr: { class: 'form-editor' },
      inputAttr: { class: 'form-editor-input' },
    };
  }
  editorOptions = {
    ...editorOptions,
    maxLength: field.maxLength,
    readOnly: isEditable ? field.readonly : true,
    disabled: field.disabled,
    onFocusIn: field.onClick,
    onValueChanged: (e: any) => {
      dispatch(FORM_EVENTS.FIELD_CHANGED, { field: field.name, value: e.value });
      field.onChange?.(e.value);
    },
    onKeyPress: field.onKeyPress,
    onItemClick: field.onSelect,
    itemRender: field.itemFormatter,
    stylingMode: 'outlined',
    ...field.editorOptions,
  };

  if (field.buttons && field.buttons.length > 0) {
    editorOptions.buttons = [...(editorOptions.buttons ?? []), ...(field.buttons ?? [])];
  }

  const className = field.hidden ? 'dx-hidden' : '';

  return (
    <SimpleItem
      key={key}
      dataField={field.name}
      editorType={getEditorType(field)}
      label={{ text: field.label }}
      isRequired={field.required}
      editorOptions={editorOptions}
      helpText={field.helpText}
      colSpan={field.col ?? 12}
      visible={field.visibleOnForm}
      render={field.contentRender}
      cssClass={className}
    >
      {buildValidationRules(field)}
    </SimpleItem>
  );
};

const buildValidationRules = (field: Field) => {
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

const getEditorType = (field: Field): any => {
  switch (field.type) {
    case FieldType.TEXT:
      return 'dxTextBox';
    case FieldType.NUMBER:
    case FieldType.CURRENCY:
      return 'dxNumberBox';
    case FieldType.DATE:
    case FieldType.DATETIME:
      return 'dxDateBox';
    case FieldType.SELECT:
    case FieldType.ENUM:
    case FieldType.ENTITY:
      return 'dxSelectBox';
    case FieldType.CHECKBOX:
      return 'dxCheckBox';
    case FieldType.RADIO:
      return 'dxRadioGroup';
    case FieldType.TEXTAREA:
      return 'dxTextArea';
    case FieldType.PASSWORD:
      return 'dxTextBox';
    case FieldType.FILE:
      return 'dxFileUploader';
    case FieldType.SWITCH:
      return 'dxSwitch';
    case FieldType.TAGS:
      return 'dxTagBox';
    case FieldType.HTML:
      return 'dxHtmlEditor';
  }
};
