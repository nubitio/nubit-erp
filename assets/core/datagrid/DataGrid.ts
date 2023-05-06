import {Constants} from "../common/Constants";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "../field/Field";
import {bus} from "../event/EventBus";
import {messenger} from "../utils/Messenger";
import {httpClient} from "../http/HttpClient";
import GridOptionsDto from "./grid.options.dto";
import DataGridUtils from "../utils/DataGridUtils";
import FilterRule from "../common/FilterRule";

export default class DataGrid {
    private container: HTMLElement;
    private fields: Field[];
    private detailFields: Field[];
    private options: any;
    private dataGrid: JQuery;
    private readonly defaultToolbar: any[];
    private toolbar: any[];
    private columns: any[];
    private detailColumns: any[];
    private routes: any = {};
    private events: any = {};

    constructor(container: string, fields: Field[], options: any) {
        this.container = document.getElementById(container) || document.body;
        this.fields = fields;
        this.detailFields = options.detailFields ?? [];
        this.options = options;
        this.defaultToolbar = [];
        this.toolbar = [];
        this.columns = [];
        this.routes = options.routes ?? {};
        this.events = options.events ?? {};
        this.init();
    }

    init() {
        this.setupView();
        this.setupColumns();
        this.setupDetailColumns();
        this.setupToolBar();
        this.setupDataGrid();
        this.setupObservers();
    }

    setupView() {
        let table = document.createElement('table');
        table.setAttribute('id', SharedUtils.randomId());
        this.container.appendChild(table);
        this.dataGrid = $('#' + table.id);
    }

    setupObservers() {
        bus.on(this.events.SUCCESS, () => this.reload());
    }

    get id() {
        return this.dataGrid.attr('id');
    }

    get grid() {
        return this.dataGrid;
    }

    get selectedRow() {
        return this.dataGrid.datagrid('getSelected') || null;
    }

    get selectedRows() {
        return this.dataGrid.datagrid('getSelections');
    }

    get data() {
        return this.dataGrid.datagrid('getData');
    }

    setupColumns() {
        this.columns = [];
        this.fields.forEach(field => {
            if (field.visible) {
                let column = {
                    field: field.name,
                    title: field.label,
                    width: field.width,
                    align: field.align,
                    sortable: field.sortable,
                    formatter: field.formatter,
                };
                this.columns.push(column);
            }
        });
    }

    setupDetailColumns() {
        this.detailColumns = [];
        this.detailFields.forEach(field => {
            if (field.visible) {
                let column = {
                    field: field.name,
                    title: field.label,
                    width: field.width,
                    align: field.align,
                    sortable: field.sortable,
                    formatter: field.formatter,
                };
                this.detailColumns.push(column);
            }
        });
    }

    setupToolBar() {
        let ref = this;

        if (this.routes.ADD || this.options.allowAdd) {
            this.defaultToolbar.push({
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    bus.emit(ref.events.ADD, {route: ref.routes.ADD});
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.routes.EDIT || this.options.allowEdit) {
            this.defaultToolbar.push({
                text: Constants.TEXT_EDIT,
                iconCls: Constants.ICON_EDIT,
                handler: function () {
                    let row = ref.dataGrid.datagrid('getSelected');
                    if (row) {
                        bus.emit(ref.events.EDIT, {route: ref.routes.EDIT, row: row});
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro para editar');
                    }
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.routes.DELETE || this.options.allowDelete) {
            this.defaultToolbar.push({
                text: Constants.TEXT_DELETE,
                iconCls: Constants.ICON_DELETE,
                handler: function () {
                    let row = ref.dataGrid.datagrid('getSelected');
                    if (row) {
                        ref.delete(ref.routes.DELETE, row);
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro para eliminar');
                    }
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.routes.EXPORT) {
            this.defaultToolbar.push({
                text: Constants.TEXT_EXPORT,
                iconCls: Constants.ICON_XLS,
                handler: function () {
                    let filterRules = ref.dataGrid.datagrid('options').filterRules
                    window.open(ref.options.routes.EXPORT + '?filterRules=' + encodeURIComponent(JSON.stringify(filterRules)));
                }
            });
            this.defaultToolbar.push('-');
        }

        this.defaultToolbar.pop();

        this.options.toolbar?.forEach((item: any) => {
            if (this.defaultToolbar.length > 0) {
                this.defaultToolbar.push('-');
            }
            this.defaultToolbar.push(item);
        });

        this.toolbar = this.defaultToolbar;
    }

    addToolbarButtons(buttons: any[]) {
        buttons.forEach((button: any) => {
            if (this.toolbar.length > 0) {
                this.toolbar.push('-');
            }
            this.toolbar.push(button);
        });
        this.dataGrid.datagrid({'toolbar': this.toolbar});
        this.dataGrid.datagrid('enableFilter', []);
    }

    getIdField() {
        let idField = this.fields.find(field => field.isIdentity);
        return idField ? idField.name : 'id';
    }

    private setupDataGrid() {
        let ref = this;
        let gridOptions: GridOptionsDto = {
            title: this.options.title,
            url: this.options.url,
            method: this.options.method ?? 'post',
            remoteFilter: true,
            toolbar: this.toolbar,
            columns: [this.columns],
            pagination: this.options.pagination ?? true,
            showFooter: this.options.showFooter ?? false,
            onLoadSuccess: function (data: any) {
                ref.options.onLoadSuccess?.(data);
            }
        }
        if (this.options.method === 'get') {
            gridOptions.loader = (param: any, success: any, error: any) => {
                let options = this.dataGrid.datagrid('options');
                if (!options.url) {
                    return false;
                }
                let defaultFilters = this.options.defaultFilters;
                let rules = JSON.parse(param.filterRules ?? '[]');
                if (defaultFilters) {
                    defaultFilters.forEach((filter: any) => {
                        rules.push(filter);
                    });
                    param.filterRules = JSON.stringify(rules);
                }
                let url = options.url + '?' + $.param(param);
                httpClient.get(url).subscribe(
                    (response) => {
                        success({
                            total: Number(response.headers['x-total-count']),
                            rows: response.data
                        });
                    }
                ).add(() => {
                    error();
                });
            }
        }
        if (this.options.detailUrl) {
            gridOptions.view = window.detailview
            gridOptions.detailFormatter = function (index: number, row: any) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            }
            gridOptions.onExpandRow = function (index: number, row: any) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                let detailsUrl = ref.options.detailUrl.replace('{id}', row[ref.getIdField()]);
                ddv.datagrid({
                    method: 'get',
                    url: detailsUrl,
                    loader: (param: any, success: any, error: any) => {
                        let options = ddv.datagrid('options');
                        if (!options.url) {
                            return false;
                        }
                        let url = options.url + '?' + $.param(param);
                        httpClient.get(url).subscribe(
                            (response) => {
                                success({
                                    total: Number(response.headers['x-total-count']),
                                    rows: response.data
                                });
                            }
                        )
                    },
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [ref.detailColumns],
                    onResize: function () {
                        ref.dataGrid.datagrid('fixDetailRowHeight', index);
                    },
                    onLoadSuccess: function () {
                        setTimeout(function () {
                            ref.dataGrid.datagrid('fixDetailRowHeight', index);
                        }, 0);
                    }
                });
                ref.dataGrid.datagrid('fixDetailRowHeight', index);
            }
        }
        this.dataGrid.datagrid(gridOptions);
        if (this.options.filter ?? true) {
            let filters: any = [];

            this.fields.forEach(field => {
                let filter = DataGridUtils.makeFilter(this.grid, field)
                if (filter) {
                    filters.push(filter);
                }
            });

            this.dataGrid.datagrid('enableFilter', filters ?? []);
        }
    }

    reload() {
        this.dataGrid.datagrid('reload');
    }

    delete(route: string, row: any) {
        let ref = this;
        messenger.confirm(
            'Confirmación',
            '¿Está seguro de eliminar el registro?',
            function (r: boolean) {
                if (r) {
                    let url = ref.options.method ? ref.options.url + '/' : route;
                    httpClient.delete<any>(url + row[ref.getIdField()] ?? '').subscribe(
                        (response) => {
                            ref.reload();
                            SharedUtils.showMessage('Info', 'Registro eliminado correctamente');
                        });
                }
            });
    }

    showLoading(msg: string) {
        this.dataGrid.datagrid('options').loadMsg = msg;
        this.dataGrid.datagrid('loading');
    }

    hideLoading() {
        this.dataGrid.datagrid('loaded');
        this.dataGrid.datagrid('options').loadMsg = Constants.TEXT_LOADING;
    }

    addFilterRule(filterRule: FilterRule) {
        this.dataGrid.datagrid('addFilterRule', filterRule);
        this.dataGrid.datagrid('doFilter');
    }
}