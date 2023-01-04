import {FormType} from "./FormType";

export interface Field {
    isIdentity: boolean;
    type: FormType;
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
    data: any[];
    formatter: any;
    visible: boolean;
    defaultValue: string | number | boolean | null;
    onChange: any;
    onSelect: any;
    onClick: any;
    readonly: boolean;
    disabled: boolean;
    hidden: boolean;
    required: boolean;
}