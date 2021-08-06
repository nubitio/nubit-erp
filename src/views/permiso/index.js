var app = app || {};
app.permisos = (function () {
    var route;
    var win = $('#permiso_window');
    var dg = $('#permiso_dg');
    var dlg = $('#permiso_dlg');
    var fm = $('#permiso_fm');
    var txt_descripcion = $('#permiso_descripcion');
    var txt_task = $('#permiso_task');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        win.window({
            title: 'Permisos',
            width: WINDOW_WIDTH,
            height: WINDOW_HEIGHT,
            inline: INLINE,
            shadow: SHADOW,
            collapsible: COLLAPSIBLE,
            minimizable: MINIMIZABLE,
            maximizable: MAXIMIZABLE,
            closable: CLOSABLE,
            border: 'thin',
            cls: 'fondo-teal-dark',
            onOpen: function () {
                win.parent().animateCss(WINDOW_OPEN_ANIMATION);
            }
        });

        dg.datagrid({
            url: 'permiso/get',
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
                sortable: true, width: 200
            }, {
                field: 'task',
                title: 'Task',
                sortable: true,
                width: 100
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dlg.dialog({
            width: DIALOG_WIDTH,
            height: DIALOG_HEIGHT,
            modal: MODAL,
            closed: CLOSED,
            inline: INLINE,
            shadow: SHADOW,
            closable: false,
            border: 'thin',
            cls: 'fondo-teal-dark',
            buttons: [{
                id: 'permiso_cancelar',
                text: TEXT_CANCEL,
                iconCls: ICON_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'permiso_guardar',
                text: TEXT_SAVE,
                iconCls: ICON_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
            }
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelAlign: 'right',
            required: true,
        });

        txt_task.textbox({
            width: '100%',
            label: 'Task',
            labelAlign: 'right',
            required: true,
        });
    };

    function nuevo() {
        fm.form('clear');
        route = 'permiso/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'permiso/edit/' + row.permiso_id;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        btn_guardar = $('#permiso_guardar');
        btn_cancelar = $('#permiso_cancelar');
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
                    $.post('permiso/delete/' + row.permiso_id)
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

    return {
        init: init
    };

})();
app.permisos.init();
