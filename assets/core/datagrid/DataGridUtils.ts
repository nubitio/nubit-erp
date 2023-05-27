import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {httpClient} from "../http/HttpClient";
import {DateUtils} from "../utils/DateUtils";
import {EnumData} from "../types";

export default class DataGridUtils {
    static makeFilter(grid: JQuery, field: Field): any {
        switch (field.type) {
            case FieldType.DATE:
                return DataGridUtils.makeDateFilter(grid, field);
            case FieldType.ENTITY:
                return DataGridUtils.makeEntityFilter(grid, field);
            case FieldType.ENUM:
                return DataGridUtils.makeEnumFilter(grid, field);
            case FieldType.SWITCH:
                return this.makeSwitchFilter(grid, field);
            default:
                return null;
        }
    }

    static makeDateFilter(grid: JQuery, field: Field): any {
        return {
            field: field.name,
            type: 'datebox',
            options: {
                panelWidth: 200,
                panelHeight: 200,
                formatter: (date: any) => DateUtils.dateFormatter(date),
                parser: (date: any) => DateUtils.dateParser(date),
                onChange: (value: any) => {
                    if (value == '') {
                        grid.datagrid('removeFilterRule', field.name);
                    } else {
                        grid.datagrid('addFilterRule', {field: field.name, op: 'equal', value: value});
                    }
                    grid.datagrid('doFilter');
                }
            },
            op: ['equal', 'contains', 'less', 'greater']
        }
    }

    static makeEntityFilter(grid: JQuery, field: Field): any {
        let timer: any;
        return {
            field: field.name,
            type: 'combobox',
            options: {
                panelHeight: 200,
                valueField: field.valueField,
                textField: field.textField,
                url: field.url,
                method: 'get',
                mode: field.mode ?? 'local',
                limitToList: true,
                selectOnNavigation: false,
                loader: (param: any, success: any, error: any) => {
                    let url = field.url;
                    if (param.q) {
                        url += '?filterRules=' + encodeURIComponent(JSON.stringify([{
                            field: field.textField,
                            op: 'contains',
                            value: param.q
                        }]));
                    } else {
                        if (url.indexOf("rows") > -1) {
                            url = url.replace(/row=\d+/, "rows=100");
                        } else {
                            if (url.indexOf("?") > -1) {
                                url += "&rows=100";
                            } else {
                                url += "?rows=100";
                            }
                        }
                    }
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        httpClient.get(url).subscribe((response: any) => {
                            response.data.unshift({[field.valueField]: '', [field.textField]: 'Todos'});
                            success(response.data);
                        });
                    }, 400);
                },
                onChange: (value: any) => {
                    timer = setTimeout(() => {
                        if (value == '') {
                            grid.datagrid('removeFilterRule', field.name);
                        } else {
                            grid.datagrid('addFilterRule', {field: field.name, op: 'equal', value: value});
                        }
                        grid.datagrid('doFilter');
                    }, 1000);
                }
            }
        }
    }

    static makeEnumFilter(grid: JQuery, field: Field): any {
        let data: EnumData[] = [];
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
