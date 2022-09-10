import {FormType} from "./FormType";
import {Field} from "./Field";

export class FieldBuilder {
    private readonly _field: Field;

    constructor() {
        this._field = {
            isIdentity: false,
            type: FormType.TEXT,
            name: '',
            label: '',
            width: null,
            align: 'left',
            sortable: false,
            validators: [],
            url: '',
            textField: '',
            valueField: '',
            data: [],
            formatter: null,
            visible: true,
            defaultValue: null,
            required: false,
        };
    }

    isIdentity(isIdentity: boolean): FieldBuilder {
        this._field.isIdentity = isIdentity;
        return this;
    }

    type(type: FormType): FieldBuilder {
        this._field.type = type;
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

    align(align: string): FieldBuilder {
        this._field.align = align;
        return this;
    }

    sortable(sortable: boolean): FieldBuilder {
        this._field.sortable = sortable;
        return this;
    }

    validators(validators: string[]): FieldBuilder {
        this._field.validators = validators;
        return this;
    }

    url(url: string): FieldBuilder {
        this._field.url = url;
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

    data(data: any[]): FieldBuilder {
        this._field.data = data;
        return this;
    }

    formatter(formatter: any): FieldBuilder {
        this._field.formatter = formatter;
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

    required(required: boolean): FieldBuilder {
        this._field.required = required;
        return this;
    }

    build(): Field {
        return this._field;
    }
}