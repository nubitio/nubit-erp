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
                width: 80
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: estado_formatter,
                styler: estado_styler
            }]],
            onDblClickRow: function () {
                app.customer.form.edit();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'estado',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'ACTIVO'
                }, {
                    value: '2',
                    text: 'INACTIVO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'estado');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'estado',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);
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