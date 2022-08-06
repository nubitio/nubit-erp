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
    formatter: any;
    visible: boolean;
    defaultValue: any;
    required: boolean;
}