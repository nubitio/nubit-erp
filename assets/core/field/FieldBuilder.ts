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
            width: 'auto',
            height: null,
            minWidth: undefined,
            minHeight: undefined,
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
            itemFormatter: null,
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

    col(col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12): FieldBuilder {
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

    width(width: number | string): FieldBuilder {
        this._field.width = width;
        return this;
    }

    height(height: number): FieldBuilder {
        this._field.height = height;
        return this;
    }

    minWidth(minWidth: number): FieldBuilder {
        this._field.minWidth = minWidth;
        return this;
    }

    minHeight(minHeight: number): FieldBuilder {
        this._field.minHeight = minHeight;
        return this;
    }

    align(align: "left" | "center" | "right"): FieldBuilder {
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

    mode(mode: "remote" | "local" | "none"): FieldBuilder {
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

    valueType(valueType: "string" | "number" | "date" | "boolean" | "object" | "datetime"): FieldBuilder {
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

    itemFormatter(itemFormatter: any): FieldBuilder {
        this._field.itemFormatter = itemFormatter;
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

    precision(value: number): FieldBuilder {
        this._field.precision = value;
        return this;
    }

    accept(value: any): FieldBuilder {
        this._field.accept = value;
        return this;
    }

    build(): Field {
        return this._field;
    }
}
