var app = app || {};
app.local = (function () {
    var route;
    var dg = $('#local_dg');
    var dlg = $('#local_dlg');
    var fm = $('#local_fm');
    var cbo_empresa = $('#local_empresa');
    var txt_descripcion = $('#local_descripcion');
    var txt_direccion = $('#local_direccion');
    var txt_telefono = $('#local_telefono');
    var cbo_estado = $('#local_estado');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        dg.datagrid({
            title: 'Locales',
            url: 'local/get',
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
                    exportar();
                }
            }],
            columns: [[{
                field: 'empresa',
                title: 'Empresa',
                sortable: true,
                width: 200
            }, {
                field: 'descripcion',
                title: 'Descripcion',
                sortable: true,
                width: 200
            }, {
                field: 'direccion',
                title: 'Dirección',
                sortable: true,
                width: 300
            }, {
                field: 'telefono',
                title: 'Telefono',
                sortable: true,
                width: 80
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: estado_formatter,
                styler: estado_styler
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'empresa',
            type: 'combobox',
            options: {
                url: 'empresa/lista/1/1',
                method: 'get',
                valueField: 'empresa_id',
                textField: 'razon_social',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'empresa_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'empresa_id',
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
                    text: 'ACTIVO'
                }, {
                    value: '2',
                    text: 'INACTIVO'
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
            buttons: [{
                id: 'local_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'local_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#local_guardar');
                btn_cancelar = $('#local_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        cbo_empresa.combobox({
            width: '100%',
            label: 'Empresa',
            labelPosition: 'top',
            url: 'empresa/lista/1/0',
            method: 'get',
            valueField: 'empresa_id',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
        });

        txt_direccion.textbox({
            width: '100%',
            label: 'Direccion',
            labelPosition: 'top',
            required: true
        });

        txt_telefono.numberbox({
            width: '100%',
            label: 'Telefono',
            labelPosition: 'top',
            validType: 'length[6,15]'
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

    };

    function nuevo() {
        fm.form('clear');
        route = 'local/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'local/edit/' + row.local_id;
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
            $.messager.confirm('Confirmar', TEXT_CONFIRM, function (r) {
                if (r) {
                    $.post('local/delete/' + row.local_id)
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

    function exportar() {
        window.open('local/exportar');
    }

    return {
        init: init
    };
})();
app.local.init();
