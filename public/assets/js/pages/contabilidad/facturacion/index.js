var app = app || {};
var accounting = app.accounting || {}
app.facturacion = (function () {
    let route;
    let dg = $('#facturacion_dg');

    let init = function () {
        dg.datagrid({
            title: 'facturacion',
            url: 'invoice/get',
            remoteFilter: true,
            toolbar: [{
                text: 'Enviar Sunat',
                iconCls: 'fas fa-paper-plane fa-1x text-warning',
                handler: function () {
                    sendInvoice();
                }
            }, '-', {
                text: 'Enviar Cliente',
                iconCls: 'fas fa-paper-plane fa-1x text-success',
                handler: function () {
                    enviar_cliente();
                }
            }, '-', {
                text: 'Ver',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    view_invoice();
                }
            }, '-', {
                text: 'Consultar CDR',
                iconCls: 'fas fa-file fa-1x text-secondary',
                handler: function () {
                    consultCdr();
                }
            }, '-', {
                text: 'Resumen diario',
                iconCls: 'fas fa-file-invoice fa-1x text-primary',
                handler: function () {
                    accounting.summary.open(1);
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                sortable: true,
                width: 100,
                align: 'center'
            }, {
                field: 'cliente_id',
                title: 'RUC/DNI',
                sortable: true
            }, {
                field: 'cliente',
                title: 'Cliente',
                sortable: true,
                width: 300
            }, {
                field: 'serie',
                title: 'Serie',
                sortable: true
            }, {
                field: 'numero',
                title: 'Numero',
                sortable: true
            }, {
                field: 'envio_sunat_nota',
                title: 'Nota Credito',
                sortable: true,
                width: 80,
                align: 'center',
                formatter: nota_formatter
            },{
                field: 'estado',
                title: 'Anulado',
                sortable: true,
                width: 80,
                align: 'center',
                formatter: anulado_formatter
            },{
                field: 'envio_sunat',
                title: 'Enviado',
                sortable: true,
                width: 80,
                align: 'center',
                formatter: sunat_formatter
            }, {
                field: 'mensaje_sunat',
                title: 'Sunat',
                sortable: true,
                width: 250,
            }]]
        });

        dg.datagrid('enableFilter', [{
            field: 'fecha',
            type: 'datebox',
            options: {
                formatter: dateformatter,
                parser: dateparser,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'fecha');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'fecha',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);
    };

    function showDataGridLoading(msg) {
        dg.datagrid('options').loadMsg = msg;
        dg.datagrid('loading');
    }

    function hideDataGridLoading() {
        dg.datagrid('loaded');
        dg.datagrid('options').loadMsg = TEXT_LOADING;
    }

    function sendInvoice() {
        let row = dg.datagrid('getSelected');
        if (row) {
            showDataGridLoading('Enviando comprobante...');
            fetch("venta/invoice/" + row.movimiento_id)
                .then(response => response.json())
                .then(result => {
                    (result.success) ? show_info(result.msg) : show_error(result.msg)
                    dg.datagrid('reload');
                })
                .catch(error => show_error(error))
                .finally(() => hideDataGridLoading())
        } else {
            show_info('Seleccione un registro');
        }
    }

    function enviar_cliente() {
        let row = dg.datagrid('getSelected');
        if (row) {
            showDataGridLoading('Enviando mensaje');
            fetch('venta/enviarefact/' + row.movimiento_id)
                .then(response => response.json())
                .then(result => show_info(result.msg))
                .catch(error => show_error(error))
                .finally(() => hideDataGridLoading());
        } else {
            show_info('Seleccione un registro');
        }
    }

    function view_invoice() {
        let row = dg.datagrid('getSelected');
        if (row) {
            window.open('invoice/' + row.movimiento_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function consultCdr() {
        let row = dg.datagrid('getSelected');
        if (row) {
            fetch('invoice/consult/' + row.movimiento_id)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        show_info(result.message);
                        dg.datagrid('reload');
                    } else {
                        show_error(result.message);
                    }
                })
        } else {
            show_info('Seleccione un registro');
        }
    }

    function anulado_formatter(value, row) {
        if (value === null) {
            return '<span class="badge rounded-pill bg-secondary">NO</span>';
        } else {
            value = Number(value);
        }
        if (value === 0) {
            return '<span class="badge rounded-pill bg-success">ENVIADO</span>';
        } else if (value < 2000) {
            return '<span class="badge rounded-pill bg-warning">ERRONEO</span>';
        } else if (value >= 2000 && value < 4000) {
            return '<span class="badge rounded-pill bg-danger">RECHAZADO</span>';
        } else if (value >= 4000) {
            return '<span class="badge rounded-pill bg-info">OBSERVADO</span>';
        }
    }

    function sunat_formatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === -1) {
            return '<span class="badge rounded-pill bg-primary">PENDIENTE</span>';
        } else if (value === 0) {
            return '<span class="badge rounded-pill bg-success">ENVIADO</span>';
        } else if (value < 2000) {
            return '<span class="badge rounded-pill bg-warning">ERRONEO</span>';
        } else if (value >= 2000 && value < 4000) {
            return '<span class="badge rounded-pill bg-danger">RECHAZADO</span>';
        } else if (value >= 4000) {
            return '<span class="badge rounded-pill bg-info">OBSERVADO</span>';
        }
    }

    return {
        init: init
    };
})();
app.facturacion.init();