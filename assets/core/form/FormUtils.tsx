import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {dxUtils} from "../common/DxUtils";
import {StorageUtils} from "../utils/StorageUtils";
import {
    CompareRule,
    CustomRule,
    EmailRule,
    FormRef,
    PatternRule,
    RequiredRule,
    SimpleItem,
    StringLengthRule
} from "devextreme-react/form";
import {MutableRefObject} from "react";
import {dispatch} from "../event/EventHook";
import {FORM_EVENTS} from "./DxForm";
import {ValidationRule} from "../field/ValidationRule";

export const buildField = (field: Field, key: any, isEditable: Boolean = true, uploadedFiles: MutableRefObject<any[]> = {current: []}, formRef: MutableRefObject<FormRef | null> = {current: null}) => {
    if (field.type === FieldType.NONE) return null;
    let editorOptions: any = {};
    if (field.type === FieldType.ENTITY) {
        editorOptions = {
            dataSource: {
                store: dxUtils.getDataStore(field.url ?? '', field.valueField, field.byKeyUrl, field.filters, [], field.loadOptions),
                paginate: true,
                pageSize: 10
            },
            valueExpr: field.valueField,
            displayExpr: field.textField,
            searchEnabled: field.searchEnabled,
            searchExpr: field.searchExpr,
            wrapItemText: true,
            showClearButton: true,
            buttons: ['clear', 'dropDown'],
        }
    }
    if (field.type === FieldType.ENUM || field.type === FieldType.SELECT) {
        editorOptions = {
            dataSource: field.data,
            valueExpr: 'value',
            displayExpr: 'text',
            buttons: ['dropDown'],
        }
    }
    if (field.type === FieldType.FILE) {
        if (field.url === undefined) {
            editorOptions = {
                accept: field.accept,
                uploadMode: 'useForm',
            }
        } else {
            editorOptions = {
                accept: field.accept,
                uploadUrl: field.url,
                name: 'file',
                onBeforeSend: (e: any) => {
                    let token = StorageUtils.getItem('token');
                    e.request.setRequestHeader('Authorization', `Bearer ${token}`)
                },
                onUploaded: (e: any) => {
                    let response = JSON.parse(e.request.response);
                    uploadedFiles.current.push({field: field, value: response.id});
                }
            }
        }
    }
    if (field.type === FieldType.DATE) {
        editorOptions = {
            type: 'date',
            displayFormat: 'dd/MM/yyyy',
            defaultValue: new Date()
        }
    }
    if (field.type === FieldType.DATETIME) {
        editorOptions = {
            type: 'datetime',
            displayFormat: 'dd/MM/yyyy HH:mm',
            defaultValue: new Date()
        }
    }
    if (field.type === FieldType.CURRENCY) {
        editorOptions = {
            format: {
                style: 'currency',
                currency: 'PEN'
            }
        }
    }
    if (field.type === FieldType.PASSWORD) {
        editorOptions = {
            mode: 'password',
            buttons: [{
                name: 'password',
                location: 'after',
                options: {
                    icon: 'ph ph-eye ',
                    stylingMode: 'text',
                    onClick: (e: any) => {
                        const editor = formRef.current?.instance().getEditor(field.name);
                        editor?.option('mode', editor.option('mode') === 'text' ? 'password' : 'text');
                        e.component.option('icon', editor?.option('mode') === 'text' ? 'ph ph-eye-slash' : 'ph ph-eye');
                    }
                }
            }]
        }
    }
    if (!isEditable) {
        editorOptions = {
            ...editorOptions,
            elementAttr: {class: 'form-editor'},
            inputAttr: {class: 'form-editor-input'}
        };
    }
    editorOptions = {
        ...editorOptions,
        maxLength: field.maxLength,
        readOnly: isEditable ? field.readonly : true,
        disabled: field.disabled,
        onFocusIn: field.onClick,
        onValueChanged: (e: any) => {
            dispatch(FORM_EVENTS.FIELD_CHANGED, {field: field.name, value: e.value})
            field.onChange?.(e.value)
        },
        onKeyPress: field.onKeyPress,
        onItemClick: field.onSelect,
        itemRender: field.itemFormatter,
        stylingMode: 'outlined'
    };

    if (field.buttons && field.buttons.length > 0) {
        editorOptions.buttons = [...editorOptions.buttons ?? [], ...field.buttons ?? []]
    }

    let className = field.hidden ? 'dx-hidden' : '';

    return (
        <SimpleItem
            key={key}
            dataField={field.name}
            editorType={getEditorType(field)}
            label={{text: field.label}}
            isRequired={field.required}
            editorOptions={editorOptions}
            helpText={field.helpText}
            colSpan={field.col ?? 12}
            visible={field.visibleOnForm}
            cssClass={className}
        >
            {buildValidationRules(field)}
        </SimpleItem>
    )
}

const buildValidationRules = (field: Field) => {
    let validationRules: ValidationRule[] = [];
    if (field.required) {
        validationRules.push({
            type: 'required',
            options: {
                message: `${field.label} es requerido`
            }
        });
    }
    validationRules = validationRules.concat(field.validators ?? []);
    return validationRules.map((rule, index) => {
        switch (rule.type) {
            case 'required':
                return <RequiredRule key={index}/>
            case 'pattern':
                return <PatternRule key={index} pattern={rule.options.pattern} message={rule.options.message}/>
            case 'email':
                return <EmailRule key={index} message={rule.options.message}/>
            case 'compare':
                return <CompareRule
                    key={index}
                    comparisonTarget={rule.options.comparisonTarget}
                    comparisonType={rule.options.comparisonType}
                    message={rule.options.message}
                />
            case 'stringLength':
                return <StringLengthRule
                    key={index}
                    min={rule.options.min}
                    max={rule.options.max}
                    message={rule.options.message}
                />
            case 'custom':
                return <CustomRule
                    key={index}
                    validationCallback={rule.options.validationCallback}
                    message={rule.options.message}
                />
            default:
                return null;
        }
    });

}

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
    }
}
