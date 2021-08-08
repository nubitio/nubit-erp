var app = app || {};
app.orden = (function () {
    var dg = $('#orden_dg');
    var btn_finalizar = $('#orden_finalizar');
    var btn_add = $('#orden_add');
    var btn_anular = $('#orden_anular');
    var db_fecha_inicio = $('#orden_fecha_inicio');
    var db_fecha_fin = $('#orden_fecha_fin');
    var btn_reporte = $('#orden_reporte');

    let route;
    let position = 1;
    let is_loading = false;
    let orden_productos;
    let dlg = $('#orden_dlg');
    let fm = $('#orden_fm');
    let tb_detalle = $('#orden_tb_detalle');
    let cbo_mesa = $('#orden_mesa');
    let txt_nota = $('#orden_nota');
    let list_template = $('#orden_template');
    let btn_guardar;
    let btn_cancelar;

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
        var xhr = $.get('producto/listafull')
            .done(function (result) {
                orden_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    var init = function () {
        load();
        dg.datagrid({
            title: 'Ordenes',
            url: 'orden/get',
            remoteFilter: true,
            toolbar: '#orden_toolbar',
            columns: [[{
                field: 'orden_id',
                title: 'Codigo',
                sortable: true,
                align: 'center'
            }, {
                field: 'fecha',
                title: 'Fecha',
                sortable: true,
                width: 150
            }, {
                field: 'mesa',
                title: 'Mesa',
                sortable: true,
                width: 100
            }, {
                field: 'nota',
                title: 'Nota',
                sortable: true,
                width: 300
            }, {
                field: 'user_create',
                title: 'Crea',
                sortable: true,
                width: 100
            }, {
                field: 'user_update',
                title: 'Modifica',
                sortable: true,
                width: 100
            }, {
                field: 'estado_cocina',
                title: 'Cocina',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: cestado_formatter
            }, {
                field: 'estado_barra',
                title: 'Barra',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: bestado_formatter
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: oestado_formatter
            }, {
                field: 'anulado',
                title: 'Anulado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: anulado_formatter
            }]],
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                var ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'orden/detalle/' + row.orden_id,
                    fitColumns: true,
                    singleSelect: true,
                    rownumbers: true,
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [[{
                        field: 'destino',
                        title: 'Destino',
                        width: 100
                    }, {
                        field: 'producto',
                        title: 'Producto',
                        width: 250
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        width: 100,
                        align: 'center'
                    }, {
                        field: 'cantidad_helper',
                        title: 'Ult. Cantidad',
                        width: 100,
                        align: 'center'
                    }, {
                        field: 'nota',
                        title: 'Nota',
                        width: 150,
                        align: 'right'
                    }, {
                        field: 'date_created',
                        title: 'Creacion',
                        width: 150,
                        align: 'center'
                    }, {
                        field: 'date_updated',
                        title: 'Actalización',
                        width: 150,
                        align: 'center'
                    }, {
                        field: 'estado',
                        title: 'Estado',
                        width: 100,
                        align: 'center',
                        formatter: odestado_formatter
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
            field: 'mesa',
            type: 'combobox',
            options: {
                url: 'mesa/lista/1/1',
                method: 'get',
                valueField: 'mesa_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'mesa_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'mesa_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'estado_cocina',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '0',
                    text: 'PENDIENTE'
                }, {
                    value: '1',
                    text: 'RECIBIDO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'estado_cocina');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'estado_cocina',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'estado_barra',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '0',
                    text: 'PENDIENTE'
                }, {
                    value: '1',
                    text: 'RECIBIDO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'estado_barra');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'estado_barra',
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
                    text: 'PENDIENTE'
                }, {
                    value: '2',
                    text: 'COMANDADO'
                }, {
                    value: '3',
                    text: 'FINALIZADO'
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
            width: 600,
            height: 400,
            buttons: [{
                id: 'orden_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'orden_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    orden_guardar();
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
                btn_guardar = $('#orden_guardar');
                btn_cancelar = $('#orden_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        btn_add.linkbutton({
            text: TEXT_ADD,
            iconCls: ICON_ADD,
            plain: true,
            onClick: function () {
                nuevo();
            }
        });

        /*btn_finalizar.linkbutton({
            text: 'Finalizar',
            iconCls: 'fa fa-check fa-lg fa-fw color-green',
            plain: true,
            onClick: function () {
                finalizar();
            }
        });*/

        btn_anular.linkbutton({
            text: 'Anular',
            iconCls: ICON_DELETE,
            plain: true,
            onClick: function () {
                anular();
            }
        });

        db_fecha_inicio.datebox({
            prompt: 'Fecha inicio',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true,
            icons: [{
                iconCls: ICON_CLEAR,
                handler: function (e) {
                    $(e.data.target).datebox('clear');
                }
            }]
        });

        db_fecha_fin.datebox({
            prompt: 'Fecha fin',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true,
            icons: [{
                iconCls: ICON_CLEAR,
                handler: function (e) {
                    $(e.data.target).datebox('clear');
                }
            }]
        });

        btn_reporte.linkbutton({
            text: 'Reporte',
            iconCls: ICON_XLS,
            plain: true,
            onClick: function () {
                exportar();
            }
        });

        db_fecha_inicio.datebox('setValue', dateformatter(firstDay));

        db_fecha_fin.datebox('setValue', 'Today');

        cbo_mesa.combobox({
            width: '100%',
            label: 'Mesa',
            labelAlign: 'right',
            url: 'mesa/lista/1/0',
            method: 'get',
            valueField: 'mesa_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_nota.textbox({
            width: '100%',
            height: 80,
            label: 'Nota',
            labelAlign: 'right',
            multiline: true
        });
    };

    function nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'orden/addweb';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function orden_guardar() {
        fm.form('submit', {
            url: route,
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
                        dlg.dialog('close');
                        dg.datagrid('reload');
                        show_info(result.msg);
                    } else {
                        show_error(result.msg);
                    }
                    enable_buttons();
                } catch (e) {
                    show_error(e);
                    enable_buttons();
                }
            }
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

    function finalizar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea finalizar esta orden?', function (r) {
                if (r) {
                    $.post('orden/finalizar/' + row.orden_id)
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

    function anular() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.prompt('Confirmar', 'Ingrese un motivo', function (r) {
                if (r) {
                    $.post('orden/anular/' + row.orden_id, {motivo: r})
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
        let cbo_producto = $('#orden_cbproducto_' + position);
        let txt_cantidad = $('#orden_tbcantidad_' + position);
        let txt_precio = $('#orden_tbprecio_' + position);
        let txt_subtotal = $('#orden_tbtotal_' + position);

        cbo_producto.combobox({
            method: 'get',
            data: orden_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '250',
            limitToList: true,
            onSelect: function (rec) {
                $.get('venta/precio/' + rec.producto_id, function (result) {
                    txt_precio.numberbox('setValue', result);
                    if (txt_cantidad.numberbox('getValue') === '') {
                        txt_cantidad.numberbox('setValue', 1);
                    }
                    orden_calcular_montos(position);
                });
            },
            required: true
        });

        txt_cantidad.numberbox({
            width: '75',
            min: 1,
            onChange: function (rec) {
                orden_calcular_montos(position);
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

    function orden_calcular_montos(position) {
        let txt_cantidad = $('#orden_tbcantidad_' + position);
        let txt_precio = $('#orden_tbprecio_' + position);
        let txt_subtotal = $('#orden_tbtotal_' + position);
        let cantidad = Number(txt_cantidad.numberbox('getValue'));
        let precio = Number(txt_precio.numberbox('getValue'));
        let subtotal = cantidad * precio;
        subtotal = subtotal.toFixed(2);
        txt_subtotal.numberbox('setValue', subtotal);
    }

    function remove_row(position) {
        $('#orden_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function cestado_formatter(value, row) {
        if (value === null) {
            value = -1;
        } else {
            value = Number(value);
        }
        if (value === 0) {
            return '<span class="badge badge-pill badge-info">PENDIENTE</span>';
        } else if (value === 1) {
            return '<span class="badge badge-pill badge-success">RECIBIDO</span>';
        } else {
            return '';
        }
    }

    function bestado_formatter(value, row) {
        if (value === null) {
            value = -1;
        } else {
            value = Number(value);
        }
        if (value === 0) {
            return '<span class="badge badge-pill badge-info">PENDIENTE</span>';
        } else if (value === 1) {
            return '<span class="badge badge-pill badge-success">RECIBIDO</span>';
        } else {
            return '';
        }
    }

    function oestado_formatter(value, row) {
        if (value === null) {
            value = -1;
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge badge-pill badge-warning">PENDIENTE</span>';
        } else if (value === 2) {
            return '<span class="badge badge-pill badge-info">COMANDADO</span>';
        } else if (value === 3) {
            return '<span class="badge badge-pill badge-success">FINALIZADO</span>';
        } else {
            return '';
        }
    }

    function odestado_formatter(value, row) {
        if (value === null) {
            value = -1;
        } else {
            value = Number(value);
        }
        if (value === 0) {
            return '<span class="badge badge-pill badge-warning">PENDIENTE</span>';
        } else if (value === 1) {
            return '<span class="badge badge-pill badge-success">PROCESADO</span>';
        } else if (value === 2) {
            return '<span class="badge badge-pill badge-danger">ELIMINADO</span>';
        } else {
            return '';
        }
    }

    function anulado_formatter(value, row) {
        if (value === null) {
            value = 2
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge badge-pill badge-danger">SI</span>';
        } else if (value === 2) {
            return '<span class="badge badge-pill badge-success">NO</span>';
        } else {
            return '';
        }
    }

    function exportar() {
        var fi = db_fecha_inicio.datebox('getValue');
        var ff = db_fecha_fin.datebox('getValue');
        if (fi && ff) {
            window.open('orden/exportar/' + fi + '/' + ff);
        } else {
            show_info('Ingrese las Fechas');
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row
    };
})();
app.orden.init();
