import {FieldType} from "./FieldType";
import {Field} from "./Field";

export class FieldBuilder {
    private readonly _field: Field;

    constructor() {
        this._field = {
            isIdentity: false,
            type: FieldType.TEXT,
            col: 12,
            name: '',
            label: '',
            width: null,
            height: null,
            align: 'left',
            sortable: true,
            filterable: true,
            validators: [],
            mode: 'local',
            url: '',
            byKeyUrl: null,
            textField: '',
            valueField: '',
            valueType: 'string',
            data: [],
            formatter: null,
            fieldFormatter: null,
            visible: true,
            defaultValue: null,
            onChange: undefined,
            onSelect: undefined,
            onClick: undefined,
            readonly: false,
            disabled: false,
            hidden: false,
            required: false,
            precision: 0,
            accept: null
        };
    }

    isIdentity(isIdentity: boolean): FieldBuilder {
        this._field.isIdentity = isIdentity;
        return this;
    }

    type(type: FieldType): FieldBuilder {
        this._field.type = type;
        return this;
    }

    col(col: number): FieldBuilder {
        this._field.col = col;
        return this;
    }

    name(name: string): FieldBuilder {
        this._field.name = name;
        return this;
    }

    label(label: string): FieldBuilder {
        this._field.label = label;
        return this;
    }

    width(width: number): FieldBuilder {
        this._field.width = width;
        return this;
    }

    height(height: number): FieldBuilder {
        this._field.height = height;
        return this;
    }

    align(align: string): FieldBuilder {
        this._field.align = align;
        return this;
    }

    sortable(sortable: boolean): FieldBuilder {
        this._field.sortable = sortable;
        return this;
    }

    filterable(filterable: boolean): FieldBuilder {
        this._field.filterable = filterable;
        return this;
    }

    validators(validators: any[]): FieldBuilder {
        this._field.validators = validators;
        return this;
    }

    mode(mode: string): FieldBuilder {
        this._field.mode = mode;
        return this;
    }

    url(url: string): FieldBuilder {
        this._field.url = url;
        return this;
    }

    byKeyUrl(byKeyUrl: string): FieldBuilder {
        this._field.byKeyUrl = byKeyUrl;
        return this;
    }

    textField(textField: string): FieldBuilder {
        this._field.textField = textField;
        return this;
    }

    valueField(valueField: string): FieldBuilder {
        this._field.valueField = valueField;
        return this;
    }

    valueType(valueType: string): FieldBuilder {
        this._field.valueType = valueType;
        return this;
    }

    data(data: any[]): FieldBuilder {
        this._field.data = data;
        return this;
    }

    formatter(formatter: any): FieldBuilder {
        this._field.formatter = formatter;
        return this;
    }

    fieldFormatter(fieldFormatter: any): FieldBuilder {
        this._field.fieldFormatter = fieldFormatter;
        return this;
    }

    visible(visible: boolean): FieldBuilder {
        this._field.visible = visible;
        return this;
    }

    defaultValue(defaultValue: any): FieldBuilder {
        this._field.defaultValue = defaultValue;
        return this;
    }

    onChange(onChange: any): FieldBuilder {
        this._field.onChange = onChange;
        return this;
    }

    onSelect(onSelect: any): FieldBuilder {
        this._field.onSelect = onSelect;
        return this;
    }

    onClick(onClick: any): FieldBuilder {
        this._field.onClick = onClick;
        return this;
    }

    readonly(readonly: boolean): FieldBuilder {
        this._field.readonly = readonly;
        return this;
    }

    disabled(disabled: boolean): FieldBuilder {
        this._field.disabled = disabled;
        return this;
    }

    hidden(hidden: boolean): FieldBuilder {
        this._field.hidden = hidden;
        return this;
    }

    required(required: boolean): FieldBuilder {
        this._field.required = required;
        return this;
    }

    precision(value:number): FieldBuilder {
        this._field.precision = value;
        return this;
    }

    accept(value:any): FieldBuilder {
        this._field.accept = value;
        return this;
    }

    build(): Field {
        return this._field;
    }
}
