var app = app || {};
app.caja = (function () {
    let route;

    let dg = $('#caja_dg');
    let dlg = $('#caja_dlg');
    let fm = $('#caja_fm');
    let fmcerrar = $('#caja_fm_pr');

    let txt_fecha = $('#caja_fecha');
    let cbo_tipo = $('#caja_tipo');
    let txt_descripcion = $('#caja_descripcion');
    let txt_referencia = $('#caja_referencia');
    let txt_monto_caja = $('#caja_monto');

    let btn_guardar;
    let btn_cancelar;

    txt_fecha.textbox({});

    let init = function () {
        caja_check_exist();
        dlg.dialog({
            width: DIALOG_WIDTH,
            height: 'auto',
            buttons: [{
                id: 'caja_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'caja_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    caja_guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#caja_guardar');
                btn_cancelar = $('#caja_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        cbo_tipo.combobox({
            width: '100%',
            label: 'Tipo',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'INGRESO'
            }, {
                value: '2',
                text: 'EGRESO'
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripción',
            labelPosition: 'top',
            required: true
        });

        txt_referencia.textbox({
            width: '100%',
            height: 60,
            label: 'Referencia',
            labelPosition: 'top',
            multiline: true
        });

        txt_monto_caja.numberbox({
            width: '100%',
            label: 'Monto',
            labelPosition: 'top',
            min: 0,
            precision: 2,
            required: true
        });
    };

    function load_datagrid(caja_id) {
        dg.datagrid({
            url: 'caja/detalle/' + caja_id,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    caja_nuevo();
                }
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    caja_editar();
                }
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    caja_eliminar();
                }
            }, '-', {
                text: 'Cerrar',
                iconCls: 'fa fa-lock fa-1x colo-red',
                handler: function () {
                    caja_cerrar();
                }
            }],
            pagination: false,
            showFooter: true,
            columns: [[{
                field: 'tipo_id',
                title: 'Tipo',
                formatter: tipo_formatter,
                styler: tipo_styler
            }, {
                field: 'descripcion',
                title: 'Descripción',
                width: 200
            }, {
                field: 'referencia',
                title: 'Referencia',
                styler: tipo_styler,
                width: 100
            }, {
                field: 'monto',
                title: 'Monto',
                align: 'right'
            }]]
        });
    }

    function caja_check_exist() {
        $.get('caja/check', function (response) {
            if (response == 'false') {
                abrir_caja();
            } else {
                let obj = response[0];
                $("#caja_id").val(obj.caja_id);
                txt_fecha.textbox('setValue', obj.fecha);
                load_datagrid(obj.caja_id);
            }
        });
    }

    function caja_nuevo() {
        fm.form('clear');
        route = 'caja/flujo';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function caja_editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            if (row.descripcion == 'FONDO DE CAJA' || row.descripcion == 'VENTA EFECTIVO' || row.descripcion == 'VENTA TARJETA') {
                $.messager.show({
                    title: 'Error',
                    msg: 'No puedes editar este registro.',
                });
            } else {
                dlg.dialog('open').dialog('setTitle', 'Editar');
                fm.form('load', row);
                route = 'caja/edit/' + row.uid + '/' + row.caja_id;
            }
        }
    }

    function caja_guardar() {
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


    function caja_eliminar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            if (row.descripcion == 'FONDO DE CAJA' || row.descripcion == 'VENTA EFECTIVO' || row.descripcion == 'VENTA TARJETA') {
                $.messager.show({
                    title: 'Error',
                    msg: 'No puedes eliminar este registro.',
                });
            } else {
                $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                    if (r) {
                        $.post('caja/delete/' + row.uid + '/' + row.caja_id)
                            .done(function (result) {
                                if (result.success) {
                                    dg.datagrid('reload');  // reload the user data
                                    $.messager.show({
                                        title: 'Info',
                                        msg: result.msg
                                    });
                                } else {
                                    $.messager.show({ // show error message
                                        title: 'Error',
                                        msg: result.msg
                                    });
                                }
                            })
                            .fail(function (xhr, status, error) {
                                $.messager.show({
                                    title: 'Error',
                                    msg: result.msg
                                });
                            });
                    }
                });
            }
        }
    }

    function caja_cerrar() {
        $.messager.confirm('Confirmar', '¿Está seguro que desea cerrar caja?', function (r) {
            if (r) {
                fmcerrar.form('submit', {
                    url: 'caja/cerrar',
                    onSubmit: function () {
                        return $(this).form('validate');
                    },
                    success: function (result) {
                        result = JSON.parse(result);
                        console.log('result');
                        if (result.success) {
                            caja_check_exist();
                            $.messager.show({
                                title: 'Info',
                                msg: result.msg
                            });
                            let count = $('#tt').tabs('tabs').length;
                            for (let i = count - 1; i >= 0; i--) {
                                $('#tt').tabs('close', i);
                            }
                        } else {
                            $.messager.show({
                                title: 'Error',
                                msg: result.msg
                            });
                        }
                    }
                });
            }
        });
    }


    function tipo_formatter(value, row) {
        if (value == 1) {
            value = 'Ingreso';
            return value;
        } else if (value == 2) {
            value = 'Egreso';
            return value;
        }
    }

    function tipo_styler(value, row, index) {
        if (value == 1 || value == 'Total Ingresos') {
            return 'background-color:#eeeeec;color:#63b343;';
        } else if (value == 2 || value == 'Total Egresos') {
            return 'background-color:#eeeeec;color:#dd4850;';
        } else if (value == 'Total Efectivo') {
            return 'background-color:#eeeeec;color:#546885;';
        }
    }

// abrir
    let fmab = $('#abrir_fm');
    let dlgab = $('#abrir_dlg');
    let txt_saldoinicial = $('#saldo_inicial');
    let btn_abrir_guardar;
    let btn_abrir_cancelar;

    dlgab.dialog({
        buttons: [{
            id: 'abrir_cancelar',
            text: TEXT_CANCEL,
            handler: function () {
                caja_check_exist();
                dlgab.dialog('close');
            }
        }, {
            id: 'abrir_guardar',
            text: TEXT_SAVE,
            handler: function () {
                caja_abrir();
            }
        }],
        onOpen: function () {
            $(this).parent().animateCss(DIALOG_OPEN_ANIMATION);
            btn_abrir_guardar = $('#abrir_guardar');
            btn_abrir_cancelar = $('#abrir_cancelar');
            btn_abrir_guardar.addClass('md-btn');
            btn_abrir_cancelar.addClass('md-btn-plain');
        }
    });

    txt_saldoinicial.textbox({
        width: '100%',
        label: 'Fondo de caja',
        labelPosition: 'top',
        mi: 0,
        precision: 2,
        required: true
    });

    function abrir_caja() {
        dlgab.dialog('open').dialog('setTitle', 'Abrir Caja');
        route = 'caja/add';
    }

    function caja_abrir() {
        fmab.form('submit', {
            url: route,
            onSubmit: function () {
                return $(this).form('validate');
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    dlgab.dialog('close');    // close the dialog
                    caja_check_exist();  // reload the user data
                    $.messager.show({
                        title: 'Info',
                        msg: result.msg
                    });
                } else {
                    $.messager.show({
                        title: 'Error',
                        msg: result.msg
                    });
                }
            }
        });
    }

    return {
        init: init
    };

})();
app.caja.init();