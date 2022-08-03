var app = app || {};
var wcppPingDelay_ms = 5000;
app.venta = (function () {
    let is_loading = false;
    let route_venta;
    let position = 1;
    let orden_id = 0;
    let venta_productos;//lista productos

    let dlg = $('#venta_dlg');
    let fm = $('#venta_fm');
    let cbo_operacion = $('#venta_operacion');
    let cbo_cliente = $('#venta_cliente');
    let txt_glosa = $('#venta_glosa');
    let cbo_pago = $('#venta_pago');
    let cbo_tarjeta = $('#venta_tarjeta');
    let tb_detalle = $('#venta_tb_detalle');
    let list_template = $('#venta_template');
    let txt_cash = $('#venta_cash');
    let txt_desc = $('#venta_descuento');
    let btn_guardar;
    let btn_cancelar;
    let txt_vuelto = $('#venta_vuelto');

    let session;
    let options;
    let dataGrid;

    function caja_check_exist() {
        fetch('caja/check')
            .then(response => response.json())
            .then(result => {
                if (!result.caja_id) {
                    $.messager.alert({
                        closable: false,
                        title: 'Registro ventas',
                        msg: 'No se encontro una caja activa para el usuario actual',
                        fn: function () {
                            loadPage('caja', 'caja');
                        }
                    });
                }
            })
            .catch(error => show_error(error));
    }

    let load = function () {
        is_loading = true;
        get_productos()
            .then(function () {
                is_loading = false;
            }).catch(function (e) {
            console.log(e);
        });
    };

    function get_productos() {
        let xhr = $.get('producto/listafull')
            .done(function (result) {
                venta_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    function getOption(name) {
        let result = options.filter(o => o.name === name)
        return result[0]
    }

    let init = function () {
        caja_check_exist();
        load();
        session = JSON.parse(localStorage.getItem('session'));
        options = JSON.parse(localStorage.getItem('options'));
        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'movimiento_id',
                isKey: true,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Fecha',
                name: 'fecha',
                required: true,
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Cliente',
                name: 'cliente',
                width: 200,
                required: true,
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Pago',
                name: 'tipo_pago',
                formatter: tipopagocell,
                required: true,
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Nota',
                name: 'serie',
                align: 'center',
                formatter: noteFormatter,
                required: true,
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Comprobante',
                name: 'iserie',
                align: 'center',
                formatter: invoiceFormatter,
                required: true,
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Total',
                name: 'gravada',
                align: 'right',
                visible: true,
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Estado',
                name: 'estado',
                align: 'center',
                formatter: statusFormatter,
                visible: true,
            }
        ]

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Ventas',
            url: 'venta/get',
            routes: {},
            subGrid: {
                url: 'venta/detalle/',
                columns: [
                    {
                        field: 'producto',
                        title: 'Producto',
                        width: 300
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        width: 100,
                        align: 'center'
                    }, {
                        field: 'precio_unitario',
                        title: 'Unitario',
                        width: 100,
                        align: 'right'
                    }, {
                        field: 'precio_total',
                        title: 'Total',
                        width: 100,
                        align: 'right'
                    }
                ]
            },
        });

        let toolbarButtons = [
            {
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    venta_nuevo(false);
                }
            },
            {
                text: 'Vista Previa',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    venta_preview();
                }
            },
            {
                text: 'Imprimir',
                iconCls: 'fas fa-print fa-1x text-primary',
                handler: function () {
                    venta_print();
                }
            },
            {
                text: 'Anular',
                iconCls: ICON_DELETE,
                handler: function () {
                    venta_anular();
                }
            },
            {
                text: 'Comprobante',
                iconCls: ICON_INVOICE,
                handler: function () {
                    makeInvoice();
                }
            }
        ];

        dataGrid.addToolbarButtons(toolbarButtons);

        dlg.dialog({
            width: 700,
            height: 500,
            buttons: [{
                id: 'venta_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'venta_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    venta_guardar();
                }
            }],
            onBeforeOpen: function () {
                if (is_loading) {
                    show_info('Cargando datos...<br>Intente nuevamente en unos segundos.');
                    return false;
                }
            },
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#venta_guardar');
                btn_cancelar = $('#venta_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        let drawer = new window.Drawer();
        let viewportHeight = window.innerHeight;
        let customer = new window.app.customer(drawer.id, {width: '100%', maxHeight: viewportHeight, modal: false});
        customer.init();
        customer.renderDialog();

        window.Observer.subscribe(window.EventType.SUCCESS, (data) => {
            cbo_cliente.combobox('reload');
            cbo_cliente.combobox('setValue', data || '');
        });

        cbo_cliente.combobox({
            width: '100%',
            label: 'Cliente',
            labelPosition: 'top',
            url: 'cliente/lista/1/0',
            method: 'get',
            valueField: 'ruc',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            required: true,
            icons: [{
                iconCls: ICON_ADD,
                handler: function (e) {
                    window.Observer.notify(window.EventType.ADD, {route: customer.ROUTES.ADD});
                    drawer.show();
                }
            }]
        });

        txt_glosa.textbox({
            width: '100%',
            height: 80,
            label: 'Obs',
            labelPosition: 'top',
            multiline: true
        });

        cbo_pago.combobox({
            width: '100%',
            label: 'Tipo Pago',
            labelPosition: 'top',
            url: 'payment-type/list/1',
            method: 'get',
            valueField: 'id',
            textField: 'name',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_cash.numberbox({
            width: 60,
            min: 0,
            precision: 2,
            onChange: function () {
                venta_vuelto();
            }
        });

        txt_desc.numberbox({
            width: 60,
            min: 0,
            precision: 2,
            onChange: function () {
                calcular_total();
            }
        });
    };

    function venta_nuevo(cargar) {
        position = 1;
        tb_detalle.empty();
        if (!cargar) {
            add_detalle();
        }
        fm.form('clear');
        route_venta = 'venta/add/2';
        $('#venta_orden_id').val(orden_id);
        cbo_operacion.val('5c02c90b-74fc-11eb-a834-005056409407'); //venta ticket
        cbo_cliente.combobox('select', '00000000');
        cbo_pago.combobox('select', 1);
        cbo_tarjeta.combobox('disable');
        txt_vuelto.html('S/ 0.00');
        $('#total').html('0.00');

        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    let venta_guardar = function () {
        fm.form('submit', {
            url: route_venta,
            onSubmit: function () {
                let valid = fm.form('validate');
                if (valid) {
                    disable_buttons();
                }
                return valid;
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    let option = getOption('app_option_invoicing_automatically');
                    let optionValue = (option.value === 'true');
                    console.log(optionValue)
                    console.log(option.value)
                    if (optionValue) {
                        let sale = {movimiento_id: result.venta.cabecera.movimiento_id, comprobante_id: null};
                        createInvoice(sale);
                    }

                    dlg.dialog('close');
                    dataGrid.reload();
                    show_info(result.msg);
                    showOptions(result.venta);
                } else {
                    show_error(result.msg);
                }
                enable_buttons();
            }
        });
    };

    function showOptions(venta) {
        try {
            let cabecera = venta.cabecera;
            imprimir_ticket(cabecera.movimiento_id, "");
        } catch (e) {
            show_error(e)
        }
    }

    function disable_buttons() {
        btn_cancelar.linkbutton('disable');
        btn_guardar.linkbutton('disable');
        btn_guardar.linkbutton({text: 'Guardando...'});
        dlg.dialog('showMask', 'Procesando...');
    }

    function enable_buttons() {
        btn_cancelar.linkbutton('enable');
        btn_guardar.linkbutton('enable');
        btn_guardar.linkbutton({text: 'Guardar'});
        dlg.dialog('hideMask');
    }

    function venta_anular() {
        let row = dataGrid.selectedRow;
        if (row) {
            $.messager.prompt('Confirmar', 'Ingrese un motivo', function (r) {
                if (r) {
                    $.post('venta/anular/' + row.movimiento_id, {motivo: r})
                        .done(function (result) {
                            if (result.success) {
                                setVoided(row);
                                dataGrid.reload();
                                show_info(result.msg);
                            } else {
                                show_error(result.msg);
                            }
                        })
                        .fail(function (xhr, status, error) {
                            show_error(error);
                        });
                }
            });
        } else {
            show_info('Seleccione un registro');
        }
    }

    function setVoided(row)
    {
        if (row.cliente_id.length == 11)
        {
            fetch('/venta/anular/voided/' + row.movimiento_id)
                .then(response => response.json())
                .then(result => (result.success) ? show_info(result.msg) : show_error(result.msg))
                .catch(error => show_error(error));
        }
        if (row.cliente_id.length == 8)
        {
            fetch('invoice/summary/sendVoided/' + row.movimiento_id)
                .then(response => response.json())
                .then(result => (result.success) ? show_info(result.msg) : show_error(result.msg))
                .catch(error => show_error(error))
        }
    }

    let add_detalle = function () {
        let template = list_template.html();
        template = template.replace(/%position%/g, position);
        tb_detalle.append(template);
        create(position, null);
        position++;
    };

    function create(position, obj) {
        let cbo_producto = $('#venta_cbproducto_' + position);
        let txt_cantidad = $('#venta_tbcantidad_' + position);
        let txt_precio = $('#venta_tbprecio_' + position);
        let txt_descuento = $('#venta_tbdescuento_' + position);
        let txt_subtotal = $('#venta_tbtotal_' + position);

        cbo_producto.combobox({
            method: 'get',
            data: venta_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '250',
            limitToList: true,
            onSelect: function (rec) {
                ind = $(this).attr('line');
                $.get('venta/precio/' + rec.producto_id, function (result) {
                    txt_precio.numberbox('setValue', result);
                    if (txt_cantidad.numberbox('getValue') === '') {
                        txt_cantidad.numberbox('setValue', 1);
                    }
                    venta_calcular_montos(position);
                });
            },
            required: true
        });

        txt_cantidad.numberbox({
            width: '60',
            min: 1,
            onChange: function (rec) {
                ind = $(this).attr('line');
                let option = getOption('app_option_stock');
                if (option.value === 'true') {
                    check_stock(position);
                }
                venta_calcular_montos(position);
            },
            required: true
        });

        let option = getOption('app_option_price');
        console.log(option)
        let optionValue = (option.value === 'true');
        console.log(optionValue)

        txt_precio.numberbox({
            width: '75',
            min: 0,
            precision: 2,
            onChange: function (rec) {
                venta_calcular_montos(position)
            },
            readonly: !optionValue,
            required: true
        });

        txt_descuento.numberbox({
            width: '60',
            min: 0,
            precision: 2,
        });

        txt_subtotal.numberbox({
            width: '75',
            required: true,
            readonly: true,
            min: 0,
            precision: 2,
        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
            txt_precio.numberbox('setValue', obj.precio_unitario);
            txt_subtotal.numberbox('setValue', obj.precio_total);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#venta_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function check_stock(position) {
        dlg.dialog('showMask', 'Consultando stock');
        let cbo_producto = $('#venta_cbproducto_' + position);
        let txt_cantidad = $('#venta_tbcantidad_' + position);
        let producto = cbo_producto.combobox('getValue');
        let cantidad = parseFloat(txt_cantidad.textbox('getValue'));
        let almacen = session.almacen_id;
        if (almacen && producto) {
            $.post('salida/stock/' + producto + '/' + almacen)
                .done(function (result) {
                    if (result < cantidad) {
                        txt_cantidad.textbox('clear');
                        show_info('Producto sin stock y/o Stock insuficiente');
                    } else {
                        show_info('Stock: ' + result);
                    }
                    dlg.dialog('hideMask');
                })
                .fail(function (xhr, status, error) {
                    show_error(error);
                    dlg.dialog('hideMask');
                });
        } else {
            txt_cantidad.textbox('clear');
            show_info('Seleccione almacen y/o producto');
        }
    }

    function venta_calcular_montos(position) {
        let txt_cantidad = $('#venta_tbcantidad_' + position);
        let txt_precio = $('#venta_tbprecio_' + position);
        let txt_subtotal = $('#venta_tbtotal_' + position);
        let cantidad = Number(txt_cantidad.numberbox('getValue'));
        let precio = Number(txt_precio.numberbox('getValue'));
        let subtotal = cantidad * precio;
        subtotal = subtotal.toFixed(2);
        txt_subtotal.numberbox('setValue', subtotal);
        calcular_total();
        venta_vuelto();
    }

    function calcular_total() {
        let sum = 0;
        let txt_total = $('#venta_total');
        $('.venta_subtotal').each(function () {
            sum += Number($(this).textbox('getValue'));
        });
        sum = sum - (sum * Number(txt_desc.numberbox('getValue')) / 100);
        sum = sum.toFixed(2);
        txt_total.html(sum);
    }

    function venta_vuelto() {
        let txt_total = $('#venta_total');
        let total = Number(txt_total.text());
        let cash = Number(txt_cash.textbox('getValue'));
        if (cash > 0) {
            let vuelto = cash - total;
            vuelto = vuelto.toFixed(2);
            txt_vuelto.html(vuelto);
        }
    }

    function tipopagocell(value, row) {
        if (value == 1) {
            return 'EFECTIVO';
        } else if (value == 2) {
            return 'TARJETA';
        } else if (value == 3) {
            return 'YAPE';
        } else {
            return '';
        }
    }

    function statusFormatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge rounded-pill bg-success">INGRESADO</span>';
        } else if (value === 2) {
            return '<span class="badge rounded-pill bg-danger">ANULADO</span>';
        } else {
            return '';
        }
    }

    function noteFormatter(value, row) {
        if (value !== null) {
            return '<a href="/sale/order/' + row.movimiento_id + '" class="text-reset" target="_blank"><i class="fas fa-receipt fa-lg"></i> ' + row.serie + '-' + row.numero + '</a>';
        } else {
            return '';
        }
    }

    function invoiceFormatter(value, row) {
        if (value !== null) {
            return '<a href="/invoice/download/' + row.movimiento_id + '/pdf?mode=preview" class="text-reset" target="_blank"><i class="fas fa-file-invoice fa-lg"></i> ' + row.iserie + '-' + row.inumero + '</a>';
        } else {
            return '';
        }
    }

    function venta_preview() {
        let row = dataGrid.selectedRow;
        if (row) {
            $('#venta_ticket').dialog({
                title: 'Ticket - Vista Previa',
                width: 300,
                height: 500,
                closed: false,
                closable: true,
                cache: false,
                inline: true,
                href: 'venta/ticket/' + row.movimiento_id,
                modal: true,
                loadingMessage: 'Cargando...',
                cls: ''
            });
        } else {
            show_info('Seleccione un registro');
        }
    }

    function venta_print() {
        let row = dataGrid.selectedRow;
        if (row) {
            jsWebClientPrint.print('useDefaultPrinter=checked&ticket=' + row.movimiento_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function imprimir_ticket(ticket, digest) {
        //jsWebClientPrint.print('useDefaultPrinter=' + $('#useDefaultPrinter').attr('checked') + '&printerName=' + $('#installedPrinterName').val());
        jsWebClientPrint.print('useDefaultPrinter=checked&ticket=' + ticket + "&digest=" + digest);
    }

    function makeInvoice() {
        let row = dataGrid.selectedRow;
        if (row) {
            let confirmMessage = '';
            if (row.cliente_id.length === 11) {
                confirmMessage = 'Se emitira una factura electrónica, ¿Desea continuar?'
            } else {
                confirmMessage = 'Se emitira una boleta electrónica, ¿Desea continuar?'
            }
            $.messager.confirm('Confirmar', confirmMessage, function (r) {
                if (r) {
                    createInvoice(row)
                }
            })
        } else {
            show_info('Seleccione un registro');
        }
    }

    function createInvoice(sale) {
        if (sale.comprobante_id === null) {
            fetch('sale/invoice/' + sale.movimiento_id)
                .then(response => response.json())
                .then(result => show_info(result.msg))
                .catch(error => show_error(error))
                .finally(() => dataGrid.reload());
        } else {
            show_info('Ya se registro el comprobante previamente');
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row,
        guardar: venta_guardar
    };
})();
app.venta.init();

function wcppDetectOnSuccess() {
    let wcppVer = arguments[0];
    if (wcppVer.substring(0, 1) == "4") {
        $.messager.show({
            title: 'WebClientPrint',
            msg: 'Preparado para imprimir.'
        });
    } else {
        wcppDetectOnFailure();
    }
}

function wcppDetectOnFailure() {
    //$.messager.alert('WebClientPrint','Instale WCPP para poder imprimir! <a href="http://www.neodynamic.com/downloads/wcpp/" target="_blank">Descargar</a>','error');
}
