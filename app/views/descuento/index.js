var app = app || {};
app.descuento = (function () {
    var route;
    var position = 1;
    var descuento_productos;//lista productos

    var win = $('#descuento_window');
    var dg = $('#descuento_dg');
    var dlg = $('#descuento_dlg');
    var fm = $('#descuento_fm');

    var txt_descripcion = $('#descuento_descripcion');
    var txt_codigo = $('#descuento_codigo');
    var db_fecha_inicio = $('#descuento_fecha_inicio');
    var db_fecha_fin = $('#descuento_fecha_fin');
    var txt_porcentaje = $('#descuento_porcentaje');
    var cbo_estado = $('#descuento_estado');

    var btn_add = $('#descuento_add');
    var tb_detalle = $('#descuento_tb_detalle');
    var list_template = $('#descuento_template');
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
        var xhr = $.get('producto/listafull')
            .done(function (result) {
                descuento_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    var init = function () {
        load();
        win.window({
            title: 'descuentos',
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
            url: 'descuento/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    descuento_nuevo();
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
            }, {
                field: 'codigo',
                title: 'Codigo',
                width: 100,
                align: 'center',
                sortable: true
            }, {
                field: 'fecha_inicio',
                title: 'Fecha inicio',
                width: 100,
                align: 'center',
                sortable: true
            }, {
                field: 'fecha_fin',
                title: 'Fecha fin',
                width: 100,
                align: 'center',
                sortable: true
            }, {
                field: 'porcentaje',
                title: 'Porcentaje',
                width: 100,
                align: 'center',
                sortable: true
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
                    url: 'descuento/detalle/' + row.descuento_id,
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
                        field: 'precio_lista',
                        title: 'Precio lista',
                        align: 'right',
                        width: 100
                    }, {
                        field: 'precio_descuento',
                        title: 'Precio descuento',
                        align: 'right',
                        width: 100
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
            width: 450,
            height: 400,
            modal: MODAL,
            closed: CLOSED,
            inline: INLINE,
            shadow: SHADOW,
            closable: false,
            border: 'thin',
            cls: 'fondo-blue-dark',
            buttons: [{
                id: 'descuento_cancelar',
                text: TEXT_CANCEL,
                iconCls: ICON_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'descuento_guardar',
                text: TEXT_SAVE,
                iconCls: ICON_SAVE,
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
            }
        });

        txt_descripcion.textbox({
            width: '90%',
            label: 'Descripcion',
            labelAlign: 'right',
            required: true
        });

        txt_codigo.textbox({
            width: '90%',
            label: 'Codigo',
            labelAlign: 'right',
            required: true
        });

        db_fecha_inicio.datebox({
            width: '90%',
            label: 'Fecha inicio',
            labelAlign: 'right',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true
        });

        db_fecha_fin.datebox({
            width: '90%',
            label: 'Fecha fin',
            labelAlign: 'right',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            required: true
        });

        txt_porcentaje.numberbox({
            width: '90%',
            label: 'Porcentaje',
            labelAlign: 'right',
            min: 0,
            required: true
        });

        btn_add.linkbutton({
            plain: true,
            onClick: function () {
                add_detalle();
            }
        });

    };

    function descuento_nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'descuento/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar ');
            fm.form('load', row);
            route = 'descuento/edit/' + row.descuento_id;
            tb_detalle.empty();
            position = 1;
            load_detalle(row.descuento_id);
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        btn_guardar = $('#descuento_guardar');
        btn_cancelar = $('#descuento_cancelar');
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
                    $.post('descuento/delete/' + row.descuento_id)
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
        $.post('descuento/detalle/' + id)
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
        var cbo_producto = $('#descuento_cbproducto_' + position);
        var txt_precio_lista = $('#descuento_txtplista_' + position);
        var txt_precio_descuento = $('#descuento_txtpdesc_' + position);

        cbo_producto.combobox({
            data: descuento_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: '235',
            limitToList: true,
            onSelect: function (rec) {
                if (txt_porcentaje.numberbox('getValue') != 0) {
                    txt_precio_lista.numberbox('setValue', rec.precio);
                } else {
                    cbo_producto.combobox('clear').textbox('textbox').focus();
                    show_info("El porcentaje no puede estar vacio");
                }
            },
            required: true
        });

        txt_precio_lista.numberbox({
            width: '50',
            min: 0,
            precision: 2,
            onChange: function (rec) {
                let precio = Number(txt_precio_lista.numberbox('getValue'));
                let porcentaje = Number(txt_porcentaje.numberbox('getValue'));
                let result = precio - (precio * porcentaje / 100);
                txt_precio_descuento.numberbox('setValue', result);
            },
            readonly: true,
            required: true
        });

        txt_precio_descuento.numberbox({
            width: '50',
            min: 0,
            precision: 2,
            readonly: true,
            required: true
        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
            txt_precio_lista.numberbox('setValue', obj.precio_lista);
            txt_precio_descuento.numberbox('setValue', obj.precio_descuento);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#descuento_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row,
    };
})();
app.descuento.init();