import {FieldType} from "./FieldType";

export interface Field {
    isIdentity: boolean;
    type: FieldType;
    col: number;
    name: string;
    label: string;
    width: string | number | undefined;
    height: number | null;
    minWidth: number | undefined;
    minHeight: number | undefined;
    align: any;
    sortable: boolean;
    filterable: boolean;
    validators: any[];
    mode: string;
    url: string;
    byKeyUrl: string | null;
    textField: string;
    valueField: string;
    valueType: "string" | "number" | "date" | "boolean" | "object" | "datetime";
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
