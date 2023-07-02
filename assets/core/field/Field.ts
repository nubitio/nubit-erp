import {FieldType} from "./FieldType";

export interface Field {
    isIdentity: boolean;
    type: FieldType;
    col: number;
    name: string;
    label: string;
    width: number | null;
    height: number | null;
    align: string;
    sortable: boolean;
    validators: string[];
    mode: string;
    url: string;
    textField: string;
    valueField: string;
    valueType: string;
    data: any[];
    formatter: any;
    fieldFormatter: any;
    visible: boolean;
    defaultValue: string | number | boolean | null;
    onChange: any;
    onSelect: any;
    onClick: any;
    readonly: boolean;
    disabled: boolean;
    hidden: boolean;
    required: boolean;
    precision: number;
    accept: any;
}
