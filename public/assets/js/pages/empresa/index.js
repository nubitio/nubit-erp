var app = app || {};
app.empresa = (function () {
    let route;
    let dg = $('#empresa_dg');
    let dlg = $('#empresa_dlg');
    let fm = $('#empresa_fm');
    //dlg
    let txt_ruc = $('#empresa_ruc');
    let txt_razon_social = $('#empresa_razon_social');
    let txt_domicilio_fiscal = $('#empresa_domicilio_fiscal');
    let txt_telefono = $('#empresa_telefono');
    let cbo_estado = $('#empresa_estado');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Empresas',
            url: 'empresa/get',
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
                field: 'ruc',
                title: 'Ruc',
                sortable: true,
                align: 'center'
            }, {
                field: 'razon_social',
                title: 'Razón Social',
                sortable: true,
                width: 200
            }, {
                field: 'domicilio_fiscal',
                title: 'Domicilio Fiscal',
                sortable: true,
                width: 300
            }, {
                field: 'telefono',
                title: 'Telefono',
                sortable: true,
                width: 80
            }, {
                field: 'aut_sunat',
                title: 'Aut. Sunat',
                sortable: true,
                width: 150
            }, {
                field: 'estado',
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
                id: 'empresa_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'empresa_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#empresa_guardar');
                btn_cancelar = $('#empresa_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        txt_ruc.numberbox({
            width: '100%',
            label: 'RUC',
            labelPosition: 'top',
            min: 0,
            validType: 'length[11,11]',
            invalidMessage: 'Ingrese un RUC valido.',
            required: true
        });

        txt_ruc.numberbox('textbox').attr('maxlength', '11');

        var enter_pressed = false;
        txt_ruc.textbox('textbox').bind('keydown', function (e) {
            if (e.keyCode == 13 && !enter_pressed) {
                enter_pressed = true;
                dlg.dialog('showMask', 'Consultando RUC');
                $.get('api/ruc/' + txt_ruc.textbox('getValue'))
                    .done(function (result) {
                        enter_pressed = false;
                        if (result.error) {
                            show_error(result.error);
                        } else {
                            txt_razon_social.textbox('clear');
                            txt_domicilio_fiscal.textbox('clear');
                            if (result.estado === 'ACTIVO') {
                                txt_razon_social.textbox('setValue', result.razonSocial);
                                txt_domicilio_fiscal.textbox('setValue', result.direccion);
                            } else {
                                show_error(result.razon_social + '<br>' + result.estado);
                            }
                        }
                        dlg.dialog('hideMask');
                    })
                    .fail(function (xhr, status, error) {
                        enter_pressed = false;
                        dlg.dialog('hideMask');
                        show_error(error);
                    });
            }
        });

        txt_razon_social.textbox({
            width: '100%',
            height: 80,
            label: 'Razón social',
            labelPosition: 'top',
            multiline: true,
            required: true
        });

        txt_domicilio_fiscal.textbox({
            width: '100%',
            height: 80,
            label: 'Domicilio fiscal',
            labelPosition: 'top',
            multiline: true
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

//funciones
    function nuevo() {
        fm.form('clear');
        route = 'empresa/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'empresa/edit/' + row.empresa_id;
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
                    $.post('empresa/delete/' + row.empresa_id)
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
        window.open('empresa/exportar');
    }

    return {
        init: init
    };
})();
app.empresa.init();