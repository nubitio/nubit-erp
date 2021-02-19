var app = app || {};
app.receta = (function () {
    var route;
    var position = 1;
    var receta_productos;
    var win = $('#receta_window');
    var dg = $('#receta_dg');
    var dlg = $('#receta_dlg');
    var fm = $('#receta_fm');
    //dlg
    var txt_nombre = $('#receta_nombre');
    var btn_add = $('#receta_add');
    var tb_detalle = $('#receta_tb_detalle');
    var list_template = $('#receta_template');
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
                receta_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    var init = function () {
        load();
        win.window({
            title: 'Receta',
            width: WINDOW_WIDTH,
            height: WINDOW_HEIGHT,
            inline: INLINE,
            shadow: SHADOW,
            collapsible: COLLAPSIBLE,
            minimizable: MINIMIZABLE,
            maximizable: MAXIMIZABLE,
            maximized: false,
            closable: CLOSABLE,
            border: 'thin',
            cls: 'fondo-blue-dark',
            onOpen: function () {
                win.parent().animateCss(WINDOW_OPEN_ANIMATION);
            }
        });

        dg.datagrid({
            url: 'receta/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    receta_nuevo();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    receta_editar();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    receta_eliminar();
                }
            }],
            pagination: PAGINATION,
            pageSize: PAGE_SIZE,
            striped: STRIPED,
            rownumbers: ROW_NUMBERS,
            fitColumns: true,
            fit: true,
            border: BORDER,
            singleSelect: SINGLE_SELECT,
            columns: [[{
                field: 'descripcion',
                title: 'Descripcion',
                width: 200,
                sortable: true
            }]],
            onDblClickRow: function () {
                receta_editar();
            },
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                var ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'receta/detalle/' + row.receta_id,
                    fitColumns: true,
                    singleSelect: true,
                    rownumbers: true,
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
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

        dg.datagrid('enableFilter', [{}]);

        dlg.dialog({
            width: 480,
            height: 320,
            modal: MODAL,
            closed: CLOSED,
            inline: INLINE,
            shadow: SHADOW,
            closable: false,
            border: 'thin',
            cls: 'fondo-blue-dark',
            buttons: [{
                id: 'receta_cancelar',
                text: TEXT_CANCEL,
                iconCls: ICON_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'receta_guardar',
                text: TEXT_SAVE,
                iconCls: ICON_SAVE,
                handler: function () {
                    receta_guardar();
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
            }
        });

        txt_nombre.combobox({
            width: '90%',
            label: 'Producto',
            labelAlign: 'right',
            url: 'producto/listafull',
            method: 'get',
            valueField: 'producto_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        btn_add.linkbutton({
            plain: true,
            onClick: function () {
                add_detalle();
            }
        });

    };

    function receta_nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'receta/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function receta_editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'receta/edit/' + row.receta_id;
            tb_detalle.empty();
            position = 1;
            load_detalle(row.receta_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function receta_guardar() {
        btn_guardar = $('#receta_guardar');
        btn_cancelar = $('#receta_cancelar');
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

    function receta_eliminar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('receta/delete/' + row.receta_id)
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
        $.post('receta/detalle/' + id)
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
        var cbo_producto = $('#receta_cbproducto_' + position);
        var txt_cantidad = $('#receta_tbcantidad_' + position);

        cbo_producto.combobox({
            data: receta_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '300',
            limitToList: true,
            required: true
        });

        txt_cantidad.numberbox({
            width: '70',
            min: 0,
            precision: 3

        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#receta_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function receta_reporte() {
        var row = $('#receta_dg').datagrid('getSelected');
        if (row) {
            window.open('receta/reporte/' + row.receta_id);
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row
    };
})();
app.receta.init();