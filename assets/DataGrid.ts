import {Constants} from "./Constants";
import {EventType} from "./EventType";

export default class DataGrid {
    private container: HTMLElement;
    private fields: any;
    private options: any;
    private dataGrid: JQuery;
    private toolbar: any[];
    private columns: any[];

    constructor(container, fields, options) {
        this.container = document.getElementById(container);
        this.fields = fields;
        this.options = options;
        this.dataGrid = null;
        this.toolbar = null;
        this.columns = null;
        this.init();
    }

    init() {
        this.setupView();
        this.setupColumns();
        this.setupToolBar();
        this.setupDataGrid();
    }

    setupView() {
        let table = document.createElement('table');
        table.setAttribute('id', 'data-grid');
        this.container.appendChild(table);
        this.dataGrid = $('#' + table.id);
    }

    get id() {
        return this.dataGrid.attr('id');
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

        let defaultToolbar = [{
            text: Constants.TEXT_ADD,
            iconCls: Constants.ICON_ADD,
            handler: function () {
                window.Observer.notify(EventType.ADD, {route: ref.options.routes.ADD});
            },
        }, '-', {
            text: Constants.TEXT_EDIT,
            iconCls: Constants.ICON_EDIT,
            handler: function () {
                window.Observer.notify(EventType.EDIT, {route: ref.options.routes.EDIT, id: ref.getIdField()});
            },
        }, '-', {
            text: Constants.TEXT_DELETE,
            iconCls: Constants.ICON_DELETE,
            handler: function () {
                window.Observer.notify(EventType.DELETE, {route: ref.options.routes.DELETE, id: ref.getIdField()});
            },
        }, '-', {
            text: Constants.TEXT_EXPORT,
            iconCls: Constants.ICON_XLS,
            handler: function () {
                window.Observer.notify(EventType.EXPORT, {route: ref.options.routes.EXPORT});
            }
        }];

        this.toolbar = this.options.toolbar || defaultToolbar;
    }

    getIdField() {
        let idField = this.fields.find(field => field.isKey === true);
        return idField.name;
    }

    setupDataGrid() {
        this.dataGrid.datagrid({
            title: this.options.title,
            url: this.options.url,
            remoteFilter: true,
            toolbar: this.toolbar,
            columns: [this.columns],
            onDblClickRow: function () {
                window.Observer.notify(EventType.EDIT);
            }
        });

        this.dataGrid.datagrid('enableFilter', []);
    }
}