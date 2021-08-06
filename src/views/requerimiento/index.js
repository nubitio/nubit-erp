var app = app || {};
app.requerimiento = (function () {
    var route;
    var position = 1;
    var lista_almacenes;
    var lista_productos;
    var dg = $('#requerimiento_dg');
    var dlg = $('#requerimiento_dlg');
    var fm = $('#requerimiento_fm');
    var db_fecha = $('#requerimiento_fecha');
    var cbo_almacen = $('#requerimiento_almacen');
    var cbo_plantilla = $('#requerimiento_plantilla');
    var requerimiento_tb_detalle = $('#requerimiento_tb_detalle');
    var list_template = $('#requerimiento_template');
    var btn_guardar;
    var btn_cancelar;

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
            title: 'Requerimientos',
            url: 'requerimiento/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    editar();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    eliminar();
                }
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    reporte();
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                sortable: true,
                width: 25
            }, {
                field: 'almacen',
                title: 'Almacen',
                sortable: true,
                width: 200
            }]],
            onDblClickRow: function () {
                editar();
            },
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                var ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'requerimiento/detalle/' + row.requerimiento_id,
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
        }]);

        dlg.dialog({
            width: 450,
            height: 500,
            buttons: [{
                id: 'requerimiento_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'requerimiento_guardar',
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
                btn_guardar = $('#requerimiento_guardar');
                btn_cancelar = $('#requerimiento_cancelar');
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

        cbo_plantilla.combobox({
            width: '100%',
            label: 'Plantilla',
            labelPosition: 'top',
            url: 'plantilla/lista/1/0',
            method: 'get',
            valueField: 'plantilla_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            onSelect: function (rec) {
                requerimiento_tb_detalle.empty();
                load_plantilla(rec.plantilla_id);
            }
        });
    };

    //funciones
    function nuevo() {
        position = 1;
        requerimiento_tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'requerimiento/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'requerimiento/edit/' + row.requerimiento_id;
            requerimiento_tb_detalle.empty();
            position = 1;
            load_detalle(row.requerimiento_id);
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
                    $.post('requerimiento/delete/' + row.requerimiento_id)
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
        requerimiento_tb_detalle.append(template);
        create(position, null);
        position++;
    };

    function remove_row(position) {
        $('#requerimiento_tr_' + position).remove();
        if (requerimiento_tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function load_detalle(id) {
        dlg.dialog('showMask', 'Cargando...');
        var template = list_template.html();
        $.post('requerimiento/detalle/' + id)
            .done(function (result) {
                var detalle = result;
                for (var i = 0; i < detalle.length; i++) {
                    var obj = detalle[i];
                    var row = template.replace(/%position%/g, position);
                    requerimiento_tb_detalle.append(row);
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

    function load_plantilla(id) {
        dlg.dialog('showMask', 'Cargando...');
        var template = list_template.html();
        $.post('plantilla/detalle/' + id)
            .done(function (result) {
                var detalle = result;
                for (var i = 0; i < detalle.length; i++) {
                    var obj = detalle[i];
                    var row = template.replace(/%position%/g, position);
                    requerimiento_tb_detalle.append(row);
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
        var cbo_producto = $('#requerimiento_cbproducto_' + position);
        var txt_cantidad = $('#requerimiento_tbcantidad_' + position);

        cbo_producto.combobox({
            data: lista_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '275',
            limitToList: true,
            required: true
        });

        txt_cantidad.numberbox({
            width: '70',
            min: 0,
            precision: 2,
            required: true
        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function reporte() {
        var row = dg.datagrid('getSelected');
        if (row) {
            window.open('requerimiento/reporte/' + row.requerimiento_id + '/' + row.fecha);
        } else {
            show_info('Seleccione un registro');
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row
    };
})();
app.requerimiento.init();
