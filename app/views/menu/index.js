var app = app || {};
app.menu = (function () {
    let route;
    let menu_id;
    let dlg = $('#menu_dlg');
    let dg = $('#menu_dg');
    let fm = $('#menu_fm');

    let txt_orden = $('#menu_order');
    let txt_descripcion = $('#menu_descripcion');
    let txt_link = $('#menu_link');
    let txt_icono = $('#menu_icono');
    let cbo_padre = $('#menu_padre');

    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Menu',
            url: 'menu/get',
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
            }],
            columns: [[{
                field: 'orden',
                title: 'Orden',
                sortable: true
            }, {
                field: 'descripcion',
                title: 'Titulo',
                sortable: true,
                width: 100
            }, {
                field: 'link',
                title: 'Link',
                sortable: true,
                width: 100
            }, {
                field: 'icon',
                title: 'Icono',
                sortable: true,
                width: 100
            }, {
                field: 'padre',
                title: 'Padre',
                sortable: true
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter');

        dlg.dialog({
            buttons: [{
                id: 'menu_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'menu_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#menu_guardar');
                btn_cancelar = $('#menu_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        txt_orden.numberbox({
            width: '100%',
            label: 'Orden',
            labelPosition: 'top',
            min: 0
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
        });

        txt_link.textbox({
            width: '100%',
            label: 'Link',
            labelPosition: 'top'
        });

        txt_icono.textbox({
            width: '100%',
            label: 'Icono',
            labelPosition: 'top',
            required: true
        });

        cbo_padre.combobox({
            width: '100%',
            label: 'Padre',
            labelPosition: 'top',
            url: 'menu/padres',
            method: 'get',
            valueField: 'menu_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true
        });
    };

    function nuevo() {
        fm.form('clear');
        route = 'menu/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'menu/edit/' + menu_id;
        } else {
            show_info('Selecione una Registro');
        }
    }

    function guardar() {
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

    function eliminar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('menu/delete/' + id)
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
app.menu.init();
