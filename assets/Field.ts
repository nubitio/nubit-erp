import {FormType} from "./FormType";

export interface Field {
    isIdentity: boolean;
    type: FormType;
    name: string;
    label: string;
    width: number;
    align: string;
    sortable: boolean;
    validators: string[];
    url: string;
    textField: string;
    valueField: string;
    data: any[];
    formatter: any;
    visible: boolean;
    defaultValue: any;
    onChange: any;
    onSelect: any;
    required: boolean;
}