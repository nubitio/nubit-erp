var app = app || {};
app.usuario = (function () {
    var route;
    var dg = $('#usuario_dg');
    var dlg = $('#usuario_dlg');
    var fm = $('#usuario_fm');
    var txt_usuario = $('#usuario_usuario');
    var txt_pass = $('#usuario_pass');
    var cbo_rol = $('#usuario_rol');
    var txt_pin = $('#usuario_pin');
    var cbo_estado = $('#usuario_estado');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        dg.datagrid({
            title: 'Usuarios',
            url: 'usuario/get',
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
                field: 'username',
                title: 'Usuario',
                sortable: true,
                width: 100
            }, {
                field: 'rol',
                title: 'Rol',
                sortable: true,
            }, {
                field: 'active',
                title: 'Activo',
                sortable: true,
                align: 'center',
                formatter: activeFormatter,
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', []);

        dlg.dialog({
            buttons: [{
                id: 'usuario_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'usuario_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#usuario_guardar');
                btn_cancelar = $('#usuario_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        txt_usuario.textbox({
            width: '100%',
            label: 'Usuario',
            labelPosition: 'top',
            required: true
        });


        txt_pass.textbox({
            width: '100%',
            label: 'Contraseña',
            labelPosition: 'top',
            min: 0,
            precision: 2,
            required: true
        });

        cbo_rol.combobox({
            width: '100%',
            label: 'Rol',
            labelPosition: 'top',
            url: 'rol/lista/1/0',
            method: 'get',
            valueField: 'rol_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true,
            onSelect: function (record) {
                if (record.descripcion == 'MOZO' || record.descripcion == 'BARRA' || record.descripcion == 'COCINA') {
                    txt_pin.textbox('enableValidation');
                    txt_pin.textbox('readonly', false);
                } else {
                    txt_pin.textbox('clear');
                    txt_pin.textbox('disableValidation');
                    txt_pin.textbox('readonly', true);
                }
            }
        });

        txt_pin.textbox({
            width: '100%',
            label: 'Codigo PIN',
            labelPosition: 'top',
            readonly: true,
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

        txt_pin.textbox('disableValidation');
    };

    function nuevo() {
        fm.form('clear');
        route = 'usuario/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'usuario/edit/' + row.usuario_id;
            txt_pass.textbox('clear');
            txt_pass.textbox('disableValidation');
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
                    $.post('usuario/delete/' + row.usuario_id)
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
app.usuario.init();