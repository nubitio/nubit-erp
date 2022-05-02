var app = app || {};
app.salida = (function () {
    var route;
    var position = 1;
    var lista_operaciones;
    var lista_almacenes;
    var lista_productos;
    var dg = $('#salida_dg');
    var dlg = $('#salida_dlg');
    var fm = $('#salida_fm');
    var db_fecha = $('#salida_fecha');
    var cbo_operacion = $('#salida_operacion');
    var cbo_almacen = $('#salida_almacen');
    var txt_glosa = $('#salida_glosa');
    var btn_add = $('#salida_add');
    var tb_detalle = $('#salida_tb_detalle');
    var list_template = $('#salida_template');
    var btn_guardar;
    var btn_cancelar;
    var is_loading = false;

    var load = function () {
        is_loading = true;
        get_productos()
            .then(function () {
                is_loading = false;
            }).catch(function (e) {
            console.log(e);
        });
    };

    function get_productos() {
        var xhr = $.get('producto/lista/1/0')
            .done(function (result) {
                lista_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    var init = function () {
        load();
        dg.datagrid({
            title: 'Salidas',
            url: 'salida/get/2',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                sortable: true
            }, {
                field: 'operacion',
                title: 'Operacion',
                sortable: true,
                width: 100
            }, {
                field: 'almacen',
                title: 'Almacen',
                sortable: true
            }]],
            onDblClickRow: function () {
                //editar();
            },
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                var ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'salida/detalle/' + row.movimiento_id,
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
                        width: 200
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        width: 100,
                        align: 'center'
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
            field: 'almacen',
            type: 'combobox',
            options: {
                url: 'almacen/lista/1/1',
                method: 'get',
                valueField: 'almacen_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'almacen_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'almacen_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'operacion',
            type: 'combobox',
            options: {
                url: 'operacion/lista/2/0/1',
                method: 'get',
                valueField: 'operacion_id',
                textField: 'descripcion',
                panelHeight: 100,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'operacion_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'operacion_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        dlg.dialog({
            width: 550,
            height: 450,
            buttons: [{
                id: 'salida_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'salida_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
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
                btn_guardar = $('#salida_guardar');
                btn_cancelar = $('#salida_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        db_fecha.datebox({
            width: '100%',
            label: 'Fecha',
            labelPosition: 'top',
            panelAlign: 'right',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            setValue: 'Today',
            readonly: true,
            required: true
        });

        cbo_operacion.combobox({
            width: '100%',
            label: 'Operación',
            labelPosition: 'top',
            url: 'operacion/lista/2/1/0',
            method: 'get',
            valueField: 'operacion_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        cbo_almacen.combobox({
            width: '100%',
            label: 'Almacen',
            labelPosition: 'top',
            url: 'almacen/lista/1/0',
            method: 'get',
            valueField: 'almacen_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true,
            required: true
        });

        txt_glosa.textbox({
            width: '100%',
            height: 60,
            label: 'Glosa',
            labelPosition: 'top',
            multiline: true
        });

        btn_add.linkbutton({
            plain: true,
            onClick: function () {
                add_detalle();
            }
        });
    };

    function nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'salida/add/2';
        db_fecha.datebox('setValue', 'Today');
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'salida/edit/' + row.movimiento_id;
            tb_detalle.empty();
            position = 1;
            load_detalle(row.movimiento_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        fm.form('submit', {
            url: route,
            onSubmit: function () {
                valid = fm.form('validate');
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

    function eliminar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('salida/delete/' + row.movimiento_id)
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
            $.messager.confirm('Confirmar', '¿Está seguro que desea anular este registro?', function (r) {
                if (r) {
                    $.post('salida/anular/' + row.movimiento_id)
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

    var add_detalle = function () {
        var template = list_template.html();
        template = template.replace(/%position%/g, position);
        tb_detalle.append(template);
        create(position, null);
        position++;
    };

    function load_detalle(id) {
        dlg.dialog('showMask', 'Cargando...');
        var template = list_template.html();
        $.post('salida/detalle/' + id)
            .done(function (result) {
                var detalle = result;
                for (var i = 0; i < detalle.length; i++) {
                    var obj = detalle[i];
                    var row = template.replace(/%position%/g, position);
                    tb_detalle.append(row);
                    create(position, obj);
                    position++;
                }
                dlg.dialog('hideMask');
            })
            .fail(function (xhr, status, error) {
                show_error(error);
                dlg.dialog('hideMask');
            });
    }

    function create(position, obj) {
        var cbo_producto = $('#salida_cbproducto_' + position);
        var txt_cantidad = $('#salida_tbcantidad_' + position);

        cbo_producto.combobox({
            data: lista_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '340',
            limitToList: true,
            required: true
        });

        txt_cantidad.numberbox({
            width: '90',
            min: 0,
            precision: 2,
            onChange: function () {
                check_stock(position);
            },
            required: true
        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#salida_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function check_stock(position) {
        dlg.dialog('showMask', 'Consultando stock');
        var cbo_producto = $('#salida_cbproducto_' + position);
        var txt_cantidad = $('#salida_tbcantidad_' + position);
        var producto = cbo_producto.combobox('getValue');
        var cantidad = parseFloat(txt_cantidad.textbox('getValue'));
        var almacen = cbo_almacen.combobox('getValue');
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

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row
    };
})();
app.salida.init();
