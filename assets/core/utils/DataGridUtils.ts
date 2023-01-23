import {Field} from "../form/Field";
import {FormType} from "../form/FormType";
import {httpClient} from "./HttpClient";

export default class DataGridUtils {
    static makeFilter(grid: JQuery, field: Field): any {
        switch (field.type) {
            case FormType.ENTITY:
                return DataGridUtils.makeEntityFilter(grid, field);
            case FormType.ENUM:
                return DataGridUtils.makeEnumFilter(grid, field);
            case FormType.SWITCH:
                return this.makeSwitchFilter(grid, field);
            default:
                return null;
        }
    }

    static makeEntityFilter(grid: JQuery, field: Field): any {
        return {
            field: field.name,
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                valueField: field.valueField,
                textField: field.textField,
                url: field.url,
                method: 'get',
                editable: false,
                loader: (param: any, success: any, error: any) => {
                    httpClient.get<any>(field.url, {params: param})
                        .subscribe((response: any) => {
                            response.unshift({[field.valueField]: '', [field.textField]: 'Todos'});
                            success(response);
                        });
                },
                onChange: (value: any) => {
                    if (value == '') {
                        grid.datagrid('removeFilterRule', field.name);
                    } else {
                        grid.datagrid('addFilterRule', {field: field.name, op: 'equal', value: value});
                    }
                    grid.datagrid('doFilter');
                }
            }
        }
    }

    static makeEnumFilter(grid: JQuery, field: Field): any {
        let data = [];
        data.push({value: '', text: 'Todos'});
        data = data.concat(field.data);
        return {
            field: field.name,
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: data,
                editable: false,
                onChange: (value: any) => {
                    if (value == '') {
                        grid.datagrid('removeFilterRule', field.name);
                    } else {
                        grid.datagrid('addFilterRule', {field: field.name, op: 'equal', value: value});
                    }
                    grid.datagrid('doFilter');
                }
            }
        }
    }

    static makeSwitchFilter(grid: JQuery, field: Field): any {
        return {
            field: field.name,
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{value: '', text: 'Todos'}, {value: '1', text: 'Si'}, {value: '0', text: 'No'}],
                editable: false,
                onChange: (value: any) => {
                    if (value == '') {
                        grid.datagrid('removeFilterRule', field.name);
                    } else {
                        grid.datagrid('addFilterRule', {field: field.name, op: 'equal', value: value});
                    }
                    grid.datagrid('doFilter');
                }
            }
        }
    }
}