var app = app || {};
app.caja_consulta = (function () {
    var dg = $('#cconsulta_dg');

    var init = function () {
        dg.datagrid({
            title: 'Caja consulta',
            url: 'caja/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_EXPORTAR,
                iconCls: ICON_PDF,
                handler: function () {
                    cashBoxExport();
                }
            }, '-', {
                text: TEXT_REPORTE,
                iconCls: ICON_PDF,
                handler: function () {
                    cashBoxReport();
                }
            }],
            columns: [[{
                field: 'apertura',
                title: 'Apertura',
                sortable: true,
                align: 'center',
            }, {
                field: 'cierre',
                title: 'Cierre',
                sortable: true,
                align: 'center',
            }, {
                field: 'saldo_inicial',
                title: 'Saldo inicial',
                width: 100,
                align: 'right',
            }, {
                field: 'saldo_final',
                title: 'Saldo final',
                align: 'right',
            }, {
                field: 'usuario',
                title: 'Usuario',
                sortable: true,
                align: 'center',
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                align: 'center',
                formatter: statusFormatter
            }]],
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                var ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'caja/detalle/' + row.caja_id,
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    showFooter: true,
                    columns: [[{
                        field: 'tipo_id',
                        title: 'Tipo',
                        formatter: tipo_formatter,
                        styler: tipo_styler
                    }, {
                        field: 'descripcion',
                        title: 'Descripcion',
                        width: 200
                    }, {
                        field: 'referencia',
                        title: 'Referencia',
                        styler: tipo_styler,
                        width: 100
                    }, {
                        field: 'monto',
                        title: 'Monto',
                        align: 'right'
                    }]],
                    onResize: function () {
                        dg.datagrid('fixDetailRowHeight', index);
                    },
                    onLoadSuccess: function () {
                        setTimeout(function () {
                            dg.datagrid('fixDetailRowHeight', index);
                        }, 0);
                    }
                });
                dg.datagrid('fixDetailRowHeight', index);
            }
        });

        dg.datagrid('enableFilter', [{}]);
    };

    function tipo_formatter(value, row) {
        if (value == 1) {
            value = 'Ingreso';
            return value;
        } else if (value == 2) {
            value = 'Egreso';
            return value;
        }
    }

    function tipo_styler(value, row, index) {
        if (value == 1 || value == 'Total Ingresos') {
            return 'color:#63b343;';
        } else if (value == 2 || value == 'Total Egresos') {
            return 'color:#dd4850;';
        } else if (value == 'Total Efectivo') {
            return 'color:#546885;';
        }
    }

    function statusFormatter(value, row) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge rounded-pill bg-success">ABIERTO</span>';
        } else {
            value = '<span class="badge rounded-pill bg-secondary">CERRADO</span>';
        }
        return value;
    }

    function cashBoxExport() {
        const row = dg.datagrid('getSelected');
        if (row) {
            window.open("caja/export/" + row.caja_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function cashBoxReport() {
        const row = dg.datagrid('getSelected');
        if (row) {
            window.open("caja/report/" + row.caja_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    return {
        init: init
    };

})();
app.caja_consulta.init();