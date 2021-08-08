var app = app || {};
var wcppPingDelay_ms = 5000;
app.venta = (function () {
    let is_loading = false;
    let route_venta;
    let route_cliente;
    let position = 1;
    let orden_id = 0;
    let venta_productos;//lista productos

    let dg = $('#venta_dg');
    let dlg = $('#venta_dlg');
    let fm = $('#venta_fm');
    let cbo_operacion = $('#venta_operacion');
    let cbo_cliente = $('#venta_cliente');
    let txt_glosa = $('#venta_glosa');
    let btn_addcliente = $('#venta_addcliente');
    let cbo_pago = $('#venta_pago');
    let cbo_tarjeta = $('#venta_tarjeta');
    let tb_detalle = $('#venta_tb_detalle');
    let list_template = $('#venta_template');
    let txt_cash = $('#venta_cash');
    let txt_desc = $('#venta_descuento');
    let btn_guardar;
    let btn_cancelar;
    let txt_vuelto = $('#venta_vuelto');

    let lista_ordenes = $('#venta_ordenes_lista');
    let orden_detalle;
    let splitter_dlg = $('#splitter_dlg');
    let splitter_table = $('#splitter_table');

    let cliente_dialog_obj;

    //carga inicio

    function venta_check_caja() {
        $.get('caja/check', function (response) {
            if (response == 'false') {
                $.messager.alert({
                    closable: false,
                    title: 'Registro Ventas',
                    msg: 'No se encontro una caja activa',
                    fn: function () {
                        load_page('caja', 'caja');
                    }
                });
            }
        });
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

    let init = function () {
        venta_check_caja();
        load();
        dg.datagrid({
            title: 'Ventas',
            url: 'venta/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    venta_nuevo(false);
                }
            }, '-', {
                text: 'Vista Previa',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    venta_preview();
                }
            }, '-', {
                text: 'Imprimir',
                iconCls: 'fas fa-print fa-1x text-primary',
                handler: function () {
                    venta_print();
                }
            }, '-', {
                text: 'Anular',
                iconCls: ICON_DELETE,
                handler: function () {
                    venta_anular();
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                sortable: true,
                width: 70
            }, {
                field: 'operacion',
                title: 'Operacion',
                sortable: true,
                width: 100
            }, {
                field: 'cliente',
                title: 'Cliente',
                sortable: true,
                width: 150
            }, {
                field: 'serie',
                title: 'Serie',
                sortable: true
            }, {
                field: 'numero',
                title: 'Numero',
                sortable: true
            }, {
                field: 'tipo_pago',
                title: 'Pago',
                sortable: true,
                formatter: tipopagocell
            }, {
                field: 'estado',
                title: 'Anulado',
                sortable: true,
                align: 'center',
                formatter: anulado_formatter
            }]],
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'venta/detalle/' + row.movimiento_id,
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [[{
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

        dg.datagrid('enableFilter', [{
            field: 'operacion',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: 'VENTA BOLETA',
                    text: 'VENTA BOLETA'
                }, {
                    value: 'VENTA FACTURA',
                    text: 'VENTA FACTURA'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'operacion');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'operacion',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'tipo_pago',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'EFECTIVO'
                }, {
                    value: '2',
                    text: 'TARJETA'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'tipo_pago');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'tipo_pago',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'estado',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'NO'
                }, {
                    value: '2',
                    text: 'SI'
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

        dlg.dialog({
            width: 700,
            height: 500,
            toolbar: [{
                id: 'venta_dividir',
                text: 'Dividir',
                iconCls: 'fas fa-columns fa-1x color-green',
                handler: function () {
                    dlg.dialog('close');
                    load_splitter();
                }
            }],
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

        splitter_dlg.dialog({
            width: 900,
            height: 550,
            toolbar: [{
                id: 'splitter_add',
                text: 'Agregar cuenta',
                iconCls: ICON_ADD,
                handler: function () {
                    add_split();
                }
            }],
            buttons: [{
                id: 'splitter_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    splitter_dlg.dialog('close');
                }
            }, {
                id: 'splitter_guardar',
                text: 'Finalizar',
                handler: function () {
                    splitter_dlg.dialog('close');
                }
            }],
            onOpen: function () {
                splitter_dlg.dialog('center');
                splitter_dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                let sptr_btn_guardar = $('#splitter_guardar');
                let sptr_btn_cancelar = $('#splitter_cancelar');
                sptr_btn_guardar.addClass('md-btn');
                sptr_btn_cancelar.addClass('md-btn-plain');
            }
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
                    nuevo_cliente(cbo_cliente);
                }
            }],
            onSelect: function (rec) {
                if (rec.razon_social != 'CLIENTE GENERAL' && rec.ruc.length == 11) {
                    cbo_operacion.val('37b1e294-c224-11e7-91af-5600005a2745');
                } else {
                    cbo_operacion.val('0ec9c6ae-c04f-11e7-91af-5600005a2745');
                }
            }
        });

        txt_glosa.textbox({
            width: '100%',
            label: 'Obs',
            labelPosition: 'top'
        });

        cbo_pago.combobox({
            width: '100%',
            label: 'Tipo Pago',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'EFECTIVO'
            }, {
                value: '2',
                text: 'TARJETA'
            }],
            panelHeight: 'auto',
            limitToList: true,
            onSelect: function (rec) {
                if (rec.value === '1') {
                    cbo_tarjeta.combobox('clear');
                    cbo_tarjeta.combobox('disable');
                } else if (rec.value === '2') {
                    cbo_tarjeta.combobox({required: true});
                    cbo_tarjeta.combobox('enable');
                    cbo_tarjeta.combobox('select', 1);
                }
            },
            required: true
        });

        cbo_tarjeta.combobox({
            width: '100%',
            label: 'Tarjeta',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'VISA'
            }, {
                value: '2',
                text: 'MASTERCARD'
            }],
            panelHeight: 'auto',
            limitToList: true
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

        btn_addcliente.linkbutton({
            plain: true,
            onClick: function () {
                nuevo_cliente();
            }
        });

        lista_ordenes.datalist({
            method: 'get',
            url: 'venta/ordenes',
            valueField: 'orden_id',
            textField: 'mesa',
            groupField: 'grupo',
            rowStyler: list_styler,
            lines: true,
            fit: true,
            loadMsg: 'Actualizando...',
            onClickRow: function (index, row) {
                if (row.orden_id > 0) {
                    orden_id = row.orden_id;
                    venta_nuevo(true);
                    venta_cargar_orden(row.orden_id);
                }
            }
        });
    };

    let interval = setInterval(reload_list, 10000);

    function reload_list() {
        try {
            lista_ordenes.datalist('reload');
        } catch (e) {
            console.log(e);
            clearInterval(interval);
        }
    }

    function venta_nuevo(cargar) {
        position = 1;
        tb_detalle.empty();
        if (!cargar) {
            add_detalle();
        }
        fm.form('clear');
        route_venta = 'venta/add/2';
        $('#venta_orden_id').val(orden_id);
        cbo_operacion.val('0ec9c6ae-c04f-11e7-91af-5600005a2745'); //venta boleta
        cbo_cliente.combobox('select', '00000000');
        cbo_pago.combobox('select', 1);
        cbo_tarjeta.combobox('disable');
        txt_vuelto.html('S/ 0.00');
        $('#total').html('0.00');

        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    let venta_guardar = function (frm = null) {
        if (frm != null) {
            fm = $('#split_fm_' + frm);
            let btn_split = $('#split_btn_guardar_' + frm);
            btn_split.prop('disabled', true);
            btn_split.html('Procesando...');
        } else {
            fm = $('#venta_fm');
        }
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
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        if (!result.pending) {
                            venta_cerrar_orden();
                        }
                        imprimir_ticket(result.venta.cabecera.movimiento_id, "");
                        //generar_comprobante(result.venta, true);
                        dlg.dialog('close');
                        dg.datagrid('reload');
                        show_info(result.msg);
                        if (frm != null) {
                            let btn_split = $('#split_btn_guardar_' + frm);
                            btn_split.html('Procesado');
                        }
                    } else {
                        show_error(result.msg);
                        if (frm != null) {
                            let btn_split = $('#split_btn_guardar_' + frm);
                            btn_split.prop('disabled', false);
                            btn_split.html('Guardar');
                        }
                    }
                    enable_buttons();
                } catch (e) {
                    show_error(e);
                    enable_buttons();
                }
            }
        });
    };

    function formatDate(inputDate) {
        let parts = inputDate.match(/(\d+)/g);
        let date = new Date(parts[0], parts[1] - 1, parts[2]);
        let y = date.getFullYear();
        let m = date.getMonth() + 1;
        let d = date.getDate();
        console.log((d < 10 ? ('0' + d) : d) + '-' + (m < 10 ? ('0' + m) : m) + '-' + y);
        return (d < 10 ? ('0' + d) : d) + '-' + (m < 10 ? ('0' + m) : m) + '-' + y;
    }

    function generar_comprobante(venta, print = false) {
        let data = {};
        let cabecera = venta.cabecera;
        let vdetalle = venta.detalle;
        data.facturacion = {};
        data.facturacion.Serie = cabecera.serie;
        data.facturacion.Correlativo = cabecera.numero;
        data.facturacion.Fecha = formatDate(cabecera.fecha);
        //'dd-mm-aaaa' formado de fecha
        data.facturacion.Moneda = 'PEN';
        if (cabecera.cliente != 'CLIENTE GENERAL' && cabecera.ruc_cliente.length == 11) {
            data.facturacion.TipoComprobante = '01';
        } else {
            data.facturacion.TipoComprobante = '03';
        }
        // 01: factura
        // 03: boleta

        data.facturacion.Cliente = {};
        data.facturacion.Cliente.NroDocumento = cabecera.ruc_cliente;
        if (cabecera.cliente != 'CLIENTE GENERAL' && cabecera.ruc_cliente.length == 11) {
            data.facturacion.Cliente.TipoDocumento = '6';
        } else {
            data.facturacion.Cliente.TipoDocumento = '1';
        }
        // 0: documento no domiciliado sin ruc.
        // 1: DNI
        // 4: Carnet extranjería
        // 6: RUC
        // 7: Pasaporte
        // A: Cedula diplomatica de identidad

        data.facturacion.Cliente.Nombres = cabecera.cliente;
        data.facturacion.Cliente.Direccion = cabecera.direccion_cliente;

        let igv = 0.18;
        let divisor = 1 + igv;
        let valor_venta = cabecera.total / divisor;
        let valor_igv = valor_venta * igv;

        data.facturacion.Gratuito = 0;
        data.facturacion.Gravado = valor_venta.toFixed(2);
        // Si tiene igv el monto gravado es el subtotal del documento

        data.facturacion.Inafecto = 0;
        // Si no tiene igv el monto inafecto es el total del documento

        data.facturacion.Igv = valor_igv.toFixed(2);
        // Monto de IGV del documento

        data.facturacion.Subtotal = valor_venta.toFixed(2);
        // Monto subtotal del documento

        data.facturacion.Total = cabecera.total;
        // Monto total del documento

        data.facturacion.Obs = cabecera.glosa;
        data.facturacion.Detalle = [];
        for (let i = 0; i < vdetalle.length; i++) {
            let detalle = {};
            //// Aqui puedes usar un $.each, para recorrer tu detalle o si usas objectos en javascript mucho mejor, si necesitas ayuda con codigo estoy disponible.
            detalle.Codigo = vdetalle[i].codigo;
            detalle.Producto = vdetalle[i].producto;

            detalle.Cantidad = vdetalle[i].cantidad;
            detalle.Precio = vdetalle[i].unitario;
            // Monto incluido IGV del Precio Unitario

            detalle.Importe = vdetalle[i].precio;
            // Monto subtotal incluido IGV

            let igv = 0.18;
            let divisor = 1 + igv;
            let valor_venta = detalle.Importe / divisor;
            let valor_igv = valor_venta * igv;
            detalle.Igv = valor_igv;
            // Monto IGV del precio del producto, adjunto calculo en excel

            detalle.TipoImpuesto = 10;
            data.facturacion.Detalle.push(detalle);
        }
        console.log(data);
        $.post("venta/invoice", {invoice: data.facturacion})
            .done(function (response) {
                let data = {
                    movimiento_id: cabecera.movimiento_id,
                    code: response.code,
                    message: response.msg
                };
                if (response.success) {
                    update_movimiento(data);
                } else {
                    update_movimiento(data);
                }
                if (print) {
                    imprimir_ticket(cabecera.movimiento_id, "");
                }
            })
            .fail(function (xhr, status, error) {
                let data = {
                    movimiento_id: cabecera.movimiento_id,
                    code: 500,
                    message: error
                };
                update_movimiento(data);
            });
    }

    function update_movimiento(data) {
        $.post('venta/update-venta', {data: JSON.stringify(data)})
            .done(function (result) {
                if (result.success) {
                    console.log(result);
                } else {
                    console.log(result);
                }
                dg.datagrid('reload')
            })
            .fail(function (xhr, status, error) {
                console.log(error);
            });
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

    function venta_cerrar_orden() {
        if (orden_id > 0) {
            $.post('orden/finalizar/' + orden_id)
                .done(function (result) {
                    if (result.success) {
                        orden_id = 0;
                        show_info(result.msg);
                        reload_list();
                    } else {
                        show_error(result.msg);
                    }
                })
                .fail(function (xhr, status, error) {
                    show_error(error)
                });
        }
    }

    function venta_anular() {
        let row = dg.datagrid('getSelected');
        if (row) {
            $.messager.prompt('Confirmar', 'Ingrese un motivo', function (r) {
                if (r) {
                    $.post('venta/anular/' + row.movimiento_id, {motivo: r})
                        .done(function (result) {
                            if (result.success) {
                                dg.datagrid('reload');
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
            readonly: true,
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
            readonly: true,
            onChange: function (rec) {
                ind = $(this).attr('line');
                venta_calcular_montos(position);
            },
            required: true
        });

        txt_precio.numberbox({
            width: '75',
            required: true,
            readonly: true,
            min: 0,
            precision: 2,
        });

        txt_descuento.numberbox({
            width: '60',
            readonly: true,
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
        let txt_cash = $('#venta_cash');
        let txt_total = $('#venta_total');
        let total = Number(txt_total.text());
        let cash = Number(txt_cash.textbox('getValue'));
        if (cash > 0) {
            let vuelto = cash - total;
            vuelto = vuelto.toFixed(2);
            txt_vuelto.html(vuelto);
        }
    }

    function venta_load_combos(detalle) {
        for (let i = 0; i < detalle.length; i++) {
            let obj = detalle[i];
            $('#venta_cbproducto' + (i + 1)).combobox('select', obj.producto_id);
        }
        calcular_total();
    }

    function tipopagocell(value, row) {
        if (value == 1) {
            return 'EFECTIVO';
        } else if (value == 2) {
            return 'TARJETA';
        } else {
            return '';
        }
    }

    function anulado_formatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge badge-pill badge-success">NO</span>';
        } else if (value === 2) {
            return '<span class="badge badge-pill badge-danger">SI</span>';
        } else {
            return '';
        }
    }

    function list_styler(index, row) {
        if (row.grupo === 'Atendido') {
            return 'background-color:#fefefe;color:#4CAF50;';
        } else if (row.grupo === 'Atendiendo') {
            return 'background-color:#fefefe;color:#FF9800;';
        }
    }

    function venta_preview() {
        let row = dg.datagrid('getSelected');
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
        let row = dg.datagrid('getSelected');
        if (row) {
            jsWebClientPrint.print('useDefaultPrinter=checked&ticket=' + row.movimiento_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function venta_cargar_orden(id) {
        dlg.dialog('showMask', 'Cargando...');
        let template = list_template.html();
        $.get('venta/orden/' + id)
            .done(function (result) {
                let detalle = result;
                orden_detalle = result;
                for (let i = 0; i < detalle.length; i++) {
                    let obj = detalle[i];
                    let row = template.replace(/%position%/g, position);
                    tb_detalle.append(row);
                    create(position, obj);
                    position++;
                }
                venta_load_combos(detalle);
                dlg.dialog('hideMask');
            })
            .fail(function (xhr, status, error) {
                show_error(error);
                dlg.dialog('hideMask');
            });
    }

    function imprimir_ticket(ticket, digest) {
        try {
            //jsWebClientPrint.print('useDefaultPrinter=' + $('#useDefaultPrinter').attr('checked') + '&printerName=' + $('#installedPrinterName').val());
            jsWebClientPrint.print('useDefaultPrinter=checked&ticket=' + ticket + "&digest=" + digest);
        } catch (e) {
            show_error(e);
        }
    }

    Mousetrap.bind('ins', function () {
        //add_detalle();
    });

    //formulario cliente nuevo correcto

    let dlgc = $('#venta_cliente_dlg');
    let fmc = $('#venta_cliente_fm');
    let txt_ruc = $('#ven_cliente_ruc');
    let txt_razon_social = $('#ven_cliente_razon_social');
    let txt_domicilio_fiscal = $('#ven_cliente_domicilio_fiscal');
    let txt_telefono = $('#ven_cliente_telefono');
    let txt_email = $('#ven_cliente_email');
    let cbo_estado = $('#ven_cliente_estado');
    let btn_clie_guardar;
    let btn_clie_cancelar;

    dlgc.dialog({
        buttons: [{
            id: 'cliente_cancelar',
            text: TEXT_CANCEL,
            handler: function () {
                dlgc.dialog('close');
            }
        }, {
            id: 'cliente_guardar',
            text: TEXT_SAVE,
            handler: function () {
                venta_cliente_guardar();
            }
        }],
        onOpen: function () {
            $(this).parent().animateCss(DIALOG_OPEN_ANIMATION);
            btn_clie_guardar = $('#cliente_guardar');
            btn_clie_cancelar = $('#cliente_cancelar');
            btn_clie_guardar.addClass('md-btn');
            btn_clie_cancelar.addClass('md-btn-plain');
        }
    });

    txt_ruc.textbox({
        width: '100%',
        label: 'RUC/DNI',
        labelPosition: 'top',
        min: 0,
        validType: 'length[8,11]',
        invalidMessage: 'Ingrese un RUC/DNI valido.',
        required: true
    });

    txt_razon_social.textbox({
        width: '100%',
        height: 80,
        label: 'Cliente',
        labelPosition: 'top',
        multiline: true,
        required: true
    });

    txt_domicilio_fiscal.textbox({
        width: '100%',
        height: 80,
        label: 'Domicilio',
        labelPosition: 'top',
        multiline: true
    });

    txt_telefono.numberbox({
        width: '100%',
        label: 'Telefono',
        labelPosition: 'top',
        validType: 'length[6,15]'
    });

    txt_email.textbox({
        width: '100%',
        label: 'Email',
        labelPosition: 'top',
        validType: 'email'
    });

    cbo_estado.combobox({
        width: '100%',
        label: 'Estado',
        labelPosition: 'top',
        data: [{
            value: '1',
            text: 'ACTIVO'
        }, {
            value: '2',
            text: 'INACTIVO'
        }],
        panelHeight: 'auto',
        limitToList: true,
        required: true
    });

    let enter_pressed = false;
    txt_ruc.textbox('textbox').bind('keydown', function (e) {
        if (e.keyCode == 13 && !enter_pressed) {
            enter_pressed = true;
            dlgc.dialog('showMask', 'Consultando RUC');
            $.get('https://consultapi.jgm90.com/api/v1/ruc/' + txt_ruc.textbox('getValue') + '?token=abcxyz')
                .done(function (result) {
                    enter_pressed = false;
                    if (result.error) {
                        show_error(result.error);
                    } else {
                        txt_razon_social.textbox('clear');
                        txt_domicilio_fiscal.textbox('clear');
                        if (result.estado === 'ACTIVO') {
                            txt_razon_social.textbox('setValue', result.razonSocial);
                            txt_domicilio_fiscal.textbox('setValue', result.direccion);
                        } else {
                            show_error(result.razon_social + '<br>' + result.estado);
                        }
                    }
                    dlgc.dialog('hideMask');
                })
                .fail(function (xhr, status, error) {
                    enter_pressed = false;
                    dlgc.dialog('hideMask');
                    show_error(error);
                });
        }
    });

    function nuevo_cliente(obj = null) {
        if (obj != null) {
            cliente_dialog_obj = obj;
        } else {
            cliente_dialog_obj = cbo_cliente;
        }
        fmc.form('clear');
        route_cliente = 'venta/add-cliente';
        cbo_estado.combobox('select', 1);
        dlgc.dialog('open').dialog('center').dialog('setTitle', 'Nuevo cliente');
    }

    function venta_cliente_guardar() {
        let ruc = txt_ruc.textbox('getValue');
        fmc.form('submit', {
            url: route_cliente,
            onSubmit: function () {
                valid = fmc.form('validate');
                if (valid) {
                    clie_disable_buttons();
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        dlgc.dialog('close');
                        cliente_dialog_obj.combobox('reload');
                        cliente_dialog_obj.combobox({
                            onLoadSuccess: function (param) {
                                $.get('cliente/getone/' + ruc, function (result) {
                                    console.log(result);
                                    cliente_dialog_obj.combobox('select', result.ruc);
                                });
                            }
                        });
                        show_info(result.msg);
                    } else {
                        show_error(result.msg);
                    }
                    clie_enable_buttons();
                } catch (e) {
                    show_error(e);
                    clie_enable_buttons();
                }
            }
        });
    }

    function clie_disable_buttons() {
        btn_clie_guardar.linkbutton('disable');
        btn_clie_cancelar.linkbutton('disable');
        btn_clie_cancelar.linkbutton({text: 'Guardando...'});
        dlgc.dialog('showMask', 'Procesando...');
    }

    function clie_enable_buttons() {
        btn_clie_guardar.linkbutton('enable');
        btn_clie_cancelar.linkbutton('enable');
        btn_clie_cancelar.linkbutton({text: 'Guardar'});
        dlgc.dialog('hideMask');
    }

    // splitter
    let account = 0;

    function load_splitter() {
        let head = $('#splitter_head');
        let body = $('#splitter_body');
        head.empty();
        body.empty();
        account = 0;
        splitter_dlg.dialog('open').dialog('setTitle', 'Dividir cuentas');
        add_split();
        add_split();
    }

    function add_split() {
        account++;
        let header = $('#splitter_head');
        let head_template = $('#splitter_head_template');
        let htemplate = head_template.html();
        htemplate = htemplate.replace(/%account%/g, account);
        header.append(htemplate);
        for (let i = 1; i <= account; i++) {
            $('#splitter_head_button_' + i).prop('disabled', true);
        }
        if (account > 2) {
            $('#splitter_head_button_' + account).prop('disabled', false);
            let prev_account = account - 1;
            set_buttons_state_next(prev_account);
        }

        let body = $('#splitter_body');
        let body_template = $('#splitter_body_template');
        let btemplate = body_template.html();
        btemplate = btemplate.replace(/%account%/g, account);
        body.append(btemplate);
        build_form(account);
        if (account == 1) {
            load_split_detail();
        }
    }

    function build_form(index) {
        let txt_operacion = $('#split_operacion_' + index);
        let txt_orden = $('#split_orden_' + index);
        let cbo_cliente = $('#split_cliente_' + index);
        let txt_glosa = $('#split_glosa_' + index);
        let cbo_pago = $('#split_pago_' + index);
        let cbo_tarjeta = $('#split_tarjeta_' + index);

        cbo_cliente.combobox({
            width: 320,
            label: 'Cliente',
            labelAlign: 'right',
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
                    nuevo_cliente(cbo_cliente);
                }
            }],
            onSelect: function (rec) {
                if (rec.razon_social != 'CLIENTE GENERAL' && rec.ruc.length == 11) {
                    txt_operacion.val('37b1e294-c224-11e7-91af-5600005a2745');
                } else {
                    txt_operacion.val('0ec9c6ae-c04f-11e7-91af-5600005a2745');
                }
            }
        });

        txt_glosa.textbox({
            width: 320,
            label: 'Obs',
            labelAlign: 'right'
        });

        cbo_pago.combobox({
            width: 320,
            label: 'Tipo Pago',
            labelAlign: 'right',
            data: [{
                value: '1',
                text: 'EFECTIVO'
            }, {
                value: '2',
                text: 'TARJETA'
            }],
            panelHeight: 'auto',
            limitToList: true,
            onSelect: function (rec) {
                if (rec.value === '1') {
                    cbo_tarjeta.combobox('clear');
                    cbo_tarjeta.combobox('disable');
                } else if (rec.value === '2') {
                    cbo_tarjeta.combobox({required: true});
                    cbo_tarjeta.combobox('enable');
                    cbo_tarjeta.combobox('select', 1);
                }
            },
            required: true
        });

        cbo_tarjeta.combobox({
            width: 320,
            label: 'Tarjeta',
            labelAlign: 'right',
            data: [{
                value: '1',
                text: 'VISA'
            }, {
                value: '2',
                text: 'MASTERCARD'
            }],
            panelHeight: 'auto',
            limitToList: true
        });

        route_venta = 'venta/add/2';
        txt_orden.val(orden_id);
        txt_operacion.val('0ec9c6ae-c04f-11e7-91af-5600005a2745'); //venta boleta
        cbo_cliente.combobox('select', '00000000');
        cbo_pago.combobox('select', 1);
        cbo_tarjeta.combobox('disable');
    }

    function remove_account(index) {
        let table = $('#split_tb_detalle_' + index);
        if (table.children('tr').length == 0) {
            $('#splitter_cuenta_' + index).remove();
            $('#splitter_panel_' + index).remove();
            account--;
            if (account > 2) {
                $('#splitter_head_button_' + account).prop('disabled', false);
            }
            set_buttons_state_next(account);
        } else {
            show_info("La cuenta no se puede eliminar por que no esta vacia");
        }
    }

    let counter = 0;

    function load_split_detail() {
        let table = $('#split_tb_detalle_1');
        let item_template = $('#splitter_item_template');
        for (let i = 0; i < orden_detalle.length; i++) {
            counter++;
            let template = item_template.html();
            template = template.replace(/%counter%/g, counter);
            template = template.replace(/%account%/g, 1);
            template = template.replace(/%item_cantidad%/g, orden_detalle[i].cantidad);
            template = template.replace(/%item_producto%/, orden_detalle[i].producto);
            template = template.replace(/%item_producto_id%/, orden_detalle[i].producto_id);
            template = template.replace(/%item_unitario%/g, orden_detalle[i].precio_unitario);
            template = template.replace(/%item_total%/g, orden_detalle[i].precio_total);
            table.append(template);
        }
        set_buttons_state(1);
    }

    function set_buttons_state(index) {
        console.log(index);
        for (let i = 1; i <= counter; i++) {
            $('#item_btn_back_' + i).prop('disabled', false);
        }

        if (index == 1) {
            for (let i = 1; i <= counter; i++) {
                $('#item_btn_back_' + i).prop('disabled', true);
            }
        }
    }

    function set_buttons_state_back(index) {
        console.log(index);
        for (let i = 1; i <= counter; i++) {
            $('#item_btn_back_' + i).prop('disabled', false);
        }

        if (index == 1) {
            for (let i = 1; i <= counter; i++) {
                let btn = $('#item_btn_back_' + i);
                let btn_account = btn.attr("data-account");
                console.log(btn_account);
                if (btn_account == index) {
                    btn.prop('disabled', true);
                }
            }
        }
    }

    function set_buttons_state_next(index) {
        console.log(index);
        for (let i = 1; i <= counter; i++) {
            $('#item_btn_next_' + i).prop('disabled', false);
        }

        if (index == account) {
            for (let i = 1; i <= counter; i++) {
                let btn = $('#item_btn_next_' + i);
                let btn_account = btn.attr("data-account");
                console.log(btn_account);
                if (btn_account == index) {
                    btn.prop('disabled', true);
                }
            }
        }
    }

    let move_item = function (direction, account, position) {
        let lbl_cantidad = $('#item_text_cantidad_' + position);
        let txt_cantidad = $('#item_cantidad_' + position);
        let lbl_producto = $('#item_text_producto_' + position);
        let txt_producto = $('#item_producto_' + position);
        let lbl_unitario = $('#item_text_unitario_' + position);
        let txt_unitario = $('#item_unitario_' + position);
        let lbl_total = $('#item_text_total_' + position);
        let txt_total = $('#item_total_' + position);

        let producto_id = txt_producto.val();
        let producto = lbl_producto.html();
        let cantidad = parseInt(txt_cantidad.val());
        let unitario = parseFloat(txt_unitario.val()).toFixed(2);
        let total = parseFloat(txt_total.val()).toFixed(2);

        if (direction == 1) {
            if (cantidad >= 1) {
                let new_account = Number(account) + 1;
                let table = $('#split_tb_detalle_' + new_account);
                let item_template = $('#splitter_item_template');
                let exist = $('input[data-account=' + new_account + '][value=' + producto_id + ']');
                if (exist.length !== 0) {
                    let position = exist.attr("data-position");
                    let lbl_cantidad = $('#item_text_cantidad_' + position);
                    let txt_cantidad = $('#item_cantidad_' + position);
                    let lbl_producto = $('#item_text_producto_' + position);
                    let txt_producto = $('#item_producto_' + position);
                    let lbl_unitario = $('#item_text_unitario_' + position);
                    let txt_unitario = $('#item_unitario_' + position);
                    let lbl_total = $('#item_text_total_' + position);
                    let txt_total = $('#item_total_' + position);

                    let producto_id = txt_producto.val();
                    let producto = lbl_producto.html();
                    let cantidad = parseInt(txt_cantidad.val());
                    let unitario = parseFloat(txt_unitario.val());
                    let total = parseFloat(txt_total.val());

                    let new_cantidad = Number(cantidad) + 1;
                    lbl_cantidad.html(new_cantidad);
                    txt_cantidad.val(new_cantidad);
                    let new_total = Number(new_cantidad) * Number(unitario);
                    new_total = new_total.toFixed(2);
                    lbl_total.html(new_total);
                    txt_total.val(new_total);
                } else {
                    counter++;
                    let template = item_template.html();
                    template = template.replace(/%counter%/g, counter);
                    template = template.replace(/%account%/g, new_account);
                    template = template.replace(/%item_cantidad%/g, 1);
                    template = template.replace(/%item_producto%/, producto);
                    template = template.replace(/%item_producto_id%/, producto_id);
                    template = template.replace(/%item_unitario%/g, unitario);
                    template = template.replace(/%item_total%/g, unitario);
                    table.append(template);
                }
                if (cantidad !== 1) {
                    let new_cantidad = Number(cantidad) - 1;
                    lbl_cantidad.html(new_cantidad);
                    txt_cantidad.val(new_cantidad);
                    let new_total = Number(new_cantidad) * Number(unitario);
                    new_total = new_total.toFixed(2);
                    lbl_total.html(new_total);
                    txt_total.val(new_total);
                } else {
                    $('#item_' + position).remove();
                }
                set_buttons_state_next(new_account);
            }
        } else {
            if (cantidad >= 1) {
                let new_account = Number(account) - 1;
                let table = $('#split_tb_detalle_' + new_account);
                let item_template = $('#splitter_item_template');
                let exist = $('input[data-account=' + new_account + '][value=' + producto_id + ']');
                if (exist.length !== 0) {
                    let position = exist.attr("data-position");
                    let lbl_cantidad = $('#item_text_cantidad_' + position);
                    let txt_cantidad = $('#item_cantidad_' + position);
                    let lbl_producto = $('#item_text_producto_' + position);
                    let txt_producto = $('#item_producto_' + position);
                    let lbl_unitario = $('#item_text_unitario_' + position);
                    let txt_unitario = $('#item_unitario_' + position);
                    let lbl_total = $('#item_text_total_' + position);
                    let txt_total = $('#item_total_' + position);

                    let producto_id = txt_producto.val();
                    let producto = lbl_producto.html();
                    let cantidad = parseInt(txt_cantidad.val());
                    let unitario = parseFloat(txt_unitario.val());
                    let total = parseFloat(txt_total.val());

                    let new_cantidad = Number(cantidad) + 1;
                    lbl_cantidad.html(new_cantidad);
                    txt_cantidad.val(new_cantidad);
                    let new_total = Number(new_cantidad) * Number(unitario);
                    new_total = new_total.toFixed(2);
                    lbl_total.html(new_total);
                    txt_total.val(new_total);
                } else {
                    counter++;
                    let template = item_template.html();
                    template = template.replace(/%counter%/g, counter);
                    template = template.replace(/%account%/g, new_account);
                    template = template.replace(/%item_cantidad%/g, 1);
                    template = template.replace(/%item_producto%/, producto);
                    template = template.replace(/%item_producto_id%/, producto_id);
                    template = template.replace(/%item_unitario%/g, unitario);
                    template = template.replace(/%item_total%/g, unitario);
                    table.append(template);
                }
                if (cantidad !== 1) {
                    let new_cantidad = Number(cantidad) - 1;
                    lbl_cantidad.html(new_cantidad);
                    txt_cantidad.val(new_cantidad);
                    let new_total = Number(new_cantidad) * Number(unitario);
                    new_total = new_total.toFixed(2);
                    lbl_total.html(new_total);
                    txt_total.val(new_total);
                } else {
                    $('#item_' + position).remove();
                }
                set_buttons_state_back(new_account);
            }
        }
    };

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row,
        nuevo_cliente: nuevo_cliente,
        remove_account: remove_account,
        move_item: move_item,
        guardar: venta_guardar
    };
})();
app.venta.init();

function wcppDetectOnSuccess() {
    let wcppVer = arguments[0];
    if (wcppVer.substring(0, 1) == "3") {
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
