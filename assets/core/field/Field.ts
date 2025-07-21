import { FieldType } from './FieldType';
import { DxFilterRule } from '../common/FilterRule';
import { ValidationRule } from './ValidationRule';

export type LoadOption = Record<string, any>;

export interface Field {
    isIdentity: boolean;
    type: FieldType;
    col: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | undefined;
    name: string;
    label: string;
    width: string | number | undefined;
    height: number | null;
    minWidth: number | undefined;
    minHeight: number | undefined;
    align: 'left' | 'center' | 'right' | undefined;
    sortable: boolean;
    filterable: boolean;
    hideable: boolean;
    validators: ValidationRule[];
    mode: 'remote' | 'local' | 'none';
    url: string | undefined;
    loadOptions: LoadOption[];
    filters: DxFilterRule[];
    byKeyUrl: string | null;
    textField: string;
    valueField: string;
    valueType: 'string' | 'number' | 'date' | 'boolean' | 'object' | 'datetime';
    format: string;
    selectedFilterOperation:
        | '<'
        | '<='
        | '<>'
        | '='
        | '>'
        | '>='
        | 'between'
        | 'contains'
        | 'endswith'
        | 'notcontains'
        | 'startswith'
        | undefined;
    filterValue: any;
    data: any[];
    formatter: any;
    itemFormatter: any;
    visible: boolean;
    defaultValue: string | number | boolean | Date | null | undefined | Function;
    onChange: any;
    onSelect: any;
    onClick: any;
    onKeyPress: ((e) => void) | undefined;
    readonly: boolean;
    disabled: boolean;
    hidden: boolean;
    required: boolean;
    precision: number;
    accept: any;
    buttons: any[];
    hidingPriority: number | undefined;
    searchEnabled: boolean;
    searchExpr: string[] | null;
    fixed: boolean;
    fixedPosition: 'left' | 'right' | undefined;
    helpText: string | undefined;
    contentRender: any;
    visibleOnForm: boolean;
    maxLength: number | undefined;
    multiple: boolean;
    editorOptions: any | undefined;
}
