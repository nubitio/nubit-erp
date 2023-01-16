import {Constants} from "../common/Constants";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "../form/Field";
import {bus} from "../event/EventBus";
import {Event} from "../event/Event";
import {messenger} from "../utils/Messenger";
import {httpClient} from "../utils/HttpClient";
import GridOptionsDto from "./grid.options.dto";

export default class DataGrid {
    private container: HTMLElement;
    private fields: Field[];
    private options: any;
    private dataGrid: JQuery;
    private readonly defaultToolbar: any[];
    private toolbar: any[];
    private columns: any[];
    private routes: any = {};
    private events: any = {};

    constructor(container: string, fields: Field[], options: any) {
        this.container = document.getElementById(container) || document.body;
        this.fields = fields;
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
        this.setupToolBar();
        this.setupDataGrid();
        this.setupObservers();
    }

    setupView() {
        let table = document.createElement('table');
        table.setAttribute('id', 'data-grid');
        this.container.appendChild(table);
        this.dataGrid = $('#' + table.id);
    }

    setupObservers() {
        bus.on(
            event => event.name === this.events.SUCCESS,
            (event) => {
                this.reload();
            }
        );
    }

    get id() {
        return this.dataGrid.attr('id');
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

    setupToolBar() {
        let ref = this;

        if (this.routes.ADD || this.options.allowAdd) {
            this.defaultToolbar.push({
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    bus.emit(new Event(ref.events.ADD, {route: ref.routes.ADD}));
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
                        bus.emit(new Event(ref.events.EDIT, {route: ref.routes.EDIT, row: row}));
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
        return idField ? idField.name : null;
    }

    private setupDataGrid() {
        let ref = this;
        let gridOptions: GridOptionsDto = {
            title: this.options.title,
            url: this.options.url,
            remoteFilter: true,
            toolbar: this.toolbar,
            columns: [this.columns],
            pagination: this.options.pagination ?? true,
            showFooter: this.options.showFooter ?? false,
            onLoadSuccess: function (data: any) {
                ref.options.onLoadSuccess?.(data);
            }
        }
        let subGrid = this.options.subGrid;
        if (subGrid) {
            gridOptions.view = window.detailview
            gridOptions.detailFormatter = function (index: number, row: any) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            }
            gridOptions.onExpandRow = function (index: number, row: any) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    method: 'get',
                    url: subGrid.url + row[ref.getIdField() ?? ''],
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [subGrid.columns],
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
            this.dataGrid.datagrid('enableFilter', this.options.filter ?? []);
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
                    httpClient.delete<any>(route + row[ref.getIdField() ?? '']).subscribe(
                        (response) => {
                            ref.reload();
                            SharedUtils.showMessage('Info', response.msg);
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
}