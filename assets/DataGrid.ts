import {Constants} from "./Constants";
import {EventType} from "./EventType";
import {SharedUtils} from "./SharedUtils";

export default class DataGrid {
    private container: HTMLElement;
    private fields: any;
    private options: any;
    private dataGrid: JQuery;
    private defaultToolbar: any[];
    private toolbar: any[];
    private columns: any[];

    constructor(container, fields, options) {
        this.container = document.getElementById(container);
        this.fields = fields;
        this.options = options;
        this.dataGrid = null;
        this.defaultToolbar = [];
        this.toolbar = null;
        this.columns = null;
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
        window.Observer.subscribe(EventType.SUCCESS, (data) => {
            this.reload();
        });
    }

    get id() {
        return this.dataGrid.attr('id');
    }

    get selectedRow() {
        return this.dataGrid.datagrid('getSelected') || null;
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

        if (this.options.routes.ADD) {
            this.defaultToolbar.push({
                text: Constants.TEXT_ADD,
                iconCls: Constants.ICON_ADD,
                handler: function () {
                    window.Observer.notify(EventType.ADD, {route: ref.options.routes.ADD});
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.options.routes.EDIT) {
            this.defaultToolbar.push({
                text: Constants.TEXT_EDIT,
                iconCls: Constants.ICON_EDIT,
                handler: function () {
                    let row = ref.dataGrid.datagrid('getSelected');
                    if (row) {
                        window.Observer.notify(EventType.EDIT, {route: ref.options.routes.EDIT, row: row});
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro para editar');
                    }
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.options.routes.DELETE) {
            this.defaultToolbar.push({
                text: Constants.TEXT_DELETE,
                iconCls: Constants.ICON_DELETE,
                handler: function () {
                    let row = ref.dataGrid.datagrid('getSelected');
                    if (row) {
                        window.Observer.notify(EventType.DELETE, {route: ref.options.routes.DELETE, row: row});
                    } else {
                        SharedUtils.showMessage('Info', 'Seleccione un registro para eliminar');
                    }
                }
            });
            this.defaultToolbar.push('-');
        }

        if (this.options.routes.EXPORT) {
            this.defaultToolbar.push({
                text: Constants.TEXT_EXPORT,
                iconCls: Constants.ICON_XLS,
                handler: function () {
                    window.Observer.notify(EventType.EXPORT, {route: ref.options.routes.EXPORT});
                }
            });
            this.defaultToolbar.push('-');
        }

        this.defaultToolbar.pop();

        this.toolbar = this.options.toolbar || this.defaultToolbar;
    }

    addToolbarButtons(buttons) {
        buttons.forEach(button => {
            if (this.toolbar.length > 0) {
                this.toolbar.push('-');
            }
            this.toolbar.push(button);
        });
        this.dataGrid.datagrid({'toolbar': this.toolbar});
    }

    getIdField() {
        let idField = this.fields.find(field => field.isKey === true);
        return idField.name;
    }

    private setupDataGrid() {
        let ref = this;

        this.dataGrid.datagrid({
            title: this.options.title,
            url: this.options.url,
            remoteFilter: true,
            toolbar: this.toolbar,
            columns: [this.columns],
            onDblClickRow: function (index, row) {
                window.Observer.notify(EventType.EDIT, {route: ref.options.routes.EDIT, row: row});
            }
        });

        if (this.options.subGrid) {
            this.setupSubGrid(this.options.subGrid);
        }

        this.dataGrid.datagrid('enableFilter', []);
    }

    private setupSubGrid(subGrid) {
        let ref = this;
        this.dataGrid.datagrid({
            view: window.detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: subGrid.url + row[ref.getIdField()],
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
        });
    }

    reload() {
        this.dataGrid.datagrid('reload');
    }
}