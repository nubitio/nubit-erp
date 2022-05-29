class DataGrid {
    constructor(container, fields, options) {
        this.container = document.getElementById(container);
        this.fields = fields;
        this.options = options;
        this.dataGrid = null;
        this.toolbar = null;
        this.colums = null;
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
        this.colums = [];
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
                this.colums.push(column);
            }
        });
    }

    setupToolBar() {
        let defaultToolbar = [{
            text: TEXT_ADD,
            iconCls: ICON_ADD,
            handler: function () {
                mainObserver.notify(EVENT.ADD);
            },
        }, '-', {
            text: TEXT_EDIT,
            iconCls: ICON_EDIT,
            handler: function () {
                mainObserver.notify(EVENT.EDIT);
            },
        }, '-', {
            text: TEXT_DELETE,
            iconCls: ICON_DELETE,
            handler: function () {
                mainObserver.notify(EVENT.DELETE);
            },
        }, '-', {
            text: TEXT_EXPORTAR,
            iconCls: ICON_XLS,
            handler: function () {
                mainObserver.notify(EVENT.EXPORT);
            }
        }];

        this.toolbar = this.options.toolbar || defaultToolbar;
    }


    setupDataGrid() {
        this.dataGrid.datagrid({
            title: this.options.title,
            url: this.options.url,
            remoteFilter: true,
            toolbar: this.toolbar,
            columns: [this.colums],
            onDblClickRow: function () {
                mainObserver.notify(EVENT.EDIT);
            }
        });

        this.dataGrid.datagrid('enableFilter', []);
    }
}