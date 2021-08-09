var app = app || {};
app.customer = (function () {
    let dg = $('#cliente_dg');

    let init = function () {
        mainObserver.subscribe('customer:save', () => {
            dg.datagrid('reload')
        });

        dg.datagrid({
            title: 'Clientes',
            url: 'cliente/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    app.customerForm.add();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    app.customerForm.edit();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    app.customerForm.erase();
                }
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[{
                field: 'ruc',
                title: 'Ruc',
                sortable: true,
                align: 'center'
            }, {
                field: 'razon_social',
                title: 'Razón Social',
                sortable: true,
                width: 200
            }, {
                field: 'domicilio_fiscal',
                title: 'Domicilio Fiscal',
                sortable: true,
                width: 300
            }, {
                field: 'telefono',
                title: 'Telefono',
                sortable: true,
            }, {
                field: 'estado',
                title: 'Activo',
                sortable: true,
                align: 'center',
                formatter: activeFormatter,
            }]],
            onDblClickRow: function () {
                app.customer.form.edit();
            }
        });

        dg.datagrid('enableFilter', []);
    };

    function exportar() {
        window.open('cliente/exportar');
    }

    return {
        init: init,
        dg: dg
    };
})();

app.customer.init();