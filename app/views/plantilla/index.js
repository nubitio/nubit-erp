var app = app || {};
app.plantilla = (function () {
    var route;
    var position = 1;
    var plantilla_productos;//lista productos

    var dg = $('#plantilla_dg');
    var dlg = $('#plantilla_dlg');
    var fm = $('#plantilla_fm');

    var txt_descripcion = $('#plantilla_descripcion');
    var cbo_estado = $('#plantilla_estado');

    var btn_add = $('#plantilla_add');
    var tb_detalle = $('#plantilla_tb_detalle');
    var list_template = $('#plantilla_template');
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
                plantilla_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    var init = function () {
        load();
        dg.datagrid({
            title: 'Plantillas',
            url: 'plantilla/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    plantilla_nuevo();
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
            columns: [[{
                field: 'descripcion',
                title: 'Descripcion',
                width: 200,
                sortable: true
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                formatter: estado_formatter,
                styler: estado_styler
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
                    url: 'plantilla/detalle/' + row.plantilla_id,
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
            width: 400,
            height: 450,
            buttons: [{
                id: 'plantilla_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'plantilla_guardar',
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
                btn_guardar = $('#plantilla_guardar');
                btn_cancelar = $('#plantilla_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');

            }
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
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
            editable: false,
            required: true
        });

        btn_add.linkbutton({
            plain: true,
            onClick: function () {
                add_detalle();
            }
        });

    };

    function plantilla_nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'plantilla/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar ');
            fm.form('load', row);
            route = 'plantilla/edit/' + row.plantilla_id;
            tb_detalle.empty();
            position = 1;
            load_detalle(row.plantilla_id);
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
                    $.post('plantilla/delete/' + row.plantilla_id)
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
        $.post('plantilla/detalle/' + id)
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
        var cbo_producto = $('#plantilla_cbproducto_' + position);

        cbo_producto.combobox({
            data: plantilla_productos,
            valueField: 'producto_id',
            textField: 'descripcion',
            width: 295,
            limitToList: true,
            required: true
        });

        if (obj != null) {
            cbo_producto.combobox('select', obj.producto_id);
        } else {
            cbo_producto.combobox('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#plantilla_tr_' + position).remove();
        if (tb_detalle.children('tr').length === 0) {
            add_detalle();
        }
    }

    function reporte() {
        var row = dg.datagrid('getSelected');
        if (row) {
            window.open('plantilla/reporte/' + row.plantilla_id);
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row,
    };
})();
app.plantilla.init();