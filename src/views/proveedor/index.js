var app = app || {};
app.proveedor = (function () {
    let route;
    let dg = $('#proveedor_dg');
    let dlg = $('#proveedor_dlg');
    let fm = $('#proveedor_fm');
    let txt_ruc = $('#proveedor_ruc');
    let txt_razon_social = $('#proveedor_razon_social');
    let txt_domicilio_fiscal = $('#proveedor_domicilio_fiscal');
    let txt_telefono = $('#proveedor_telefono');
    let cbo_estado = $('#proveedor_estado');
    let btn_guardar;
    let btn_cancelar;
    let enter_pressed = false;

    let init = function () {
        dg.datagrid({
            title: 'Proveedores',
            url: 'proveedor/get',
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
            columns: [[
                {field: 'ruc', title: 'Ruc', sortable: true, align: 'center'},
                {field: 'razon_social', title: 'Razón Social', sortable: true, width: 200},
                {field: 'domicilio_fiscal', title: 'Domicilio Fiscal', sortable: true, width: 300},
                {field: 'telefono', title: 'Telefono', sortable: true, width: 80},
                {
                    field: 'estado',
                    title: 'Estado',
                    sortable: true,
                    width: 100,
                    align: 'center',
                    formatter: estado_formatter,
                    styler: estado_styler
                }
            ]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', [{
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
                id: 'proveedor_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'proveedor_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#proveedor_guardar');
                btn_cancelar = $('#proveedor_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
                console.log({btn_cancelar, btn_guardar});
            }
        });

        txt_ruc.numberbox({
            width: '100%',
            label: 'RUC',
            labelPosition: 'top',
            min: 0,
            validType: 'length[11,11]',
            invalidMessage: 'Ingrese un RUC valido.',
            icons: [{
                iconCls: ICON_SEACH,
                handler: function (e) {
                    consult();
                }
            }],
            required: true
        });

        txt_ruc.numberbox('textbox').attr('maxlength', '11');

        txt_ruc.textbox('textbox').bind('keydown', function (e) {
            if (e.keyCode === 13 && !enter_pressed) {
                consult();
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

    // functions
    function consult() {
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
                        show_error(result.razonSocial + '<br>' + result.estado);
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

    function nuevo() {
        fm.form('clear');
        route = 'proveedor/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'proveedor/edit/' + row.proveedor_id;
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
                        show_info('Ya existe el número de Ruc Ingresado ...');
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
                    $.post('proveedor/delete/' + row.proveedor_id)
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
        window.open('proveedor/exportar');
    }

    mainObserver.subscribe('proveedores:save', function (data) {
        console.log(data);
    });

    return {
        init: init
    };

})();
app.proveedor.init();
