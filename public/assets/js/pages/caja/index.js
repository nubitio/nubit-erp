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
            label: 'Concepto',
            labelPosition: 'top',
            validType: 'checkProtected[PROTECTED_CASHBOX]',
            required: true
        });

        txt_referencia.textbox({
            width: '100%',
            height: 80,
            label: 'Observaciones',
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

    function load_datagrid(caja) {
        dg.datagrid({
            title: `Caja - ${caja.fecha}`,
            url: 'caja/detalle/' + caja.caja_id,
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
                text: 'Cerrar caja',
                iconCls: 'fa fa-lock fa-1x text-info',
                handler: function () {
                    caja_cerrar();
                }
            }],
            pagination: false,
            showFooter: true,
            columns: [[{
                field: 'tipo_id',
                title: 'Tipo',
                formatter: typeFormatter
            }, {
                field: 'descripcion',
                title: 'Concepto',
            }, {
                field: 'referencia',
                title: 'Observaciones',
                width: 100,
                formatter: footerFormatter
            }, {
                field: 'monto',
                title: 'Monto',
                align: 'right'
            }]]
        });
    }

    function caja_check_exist() {
        fetch('caja/check')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    $("#caja_id").val(result.data.caja_id);
                    load_datagrid(result.data);
                } else {
                    abrir_caja();
                }
            })
            .catch(error => show_error(error));
    }

    function caja_nuevo() {
        fm.form('clear');
        route = 'caja/flujo';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function caja_editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            if (PROTECTED_CASHBOX.includes(row.descripcion)) {
                show_error('No puedes editar este registro.')
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
                let valid = fm.form('validate');
                if (valid) {
                    disable_buttons(btn_guardar, btn_cancelar, dlg);
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
                    enable_buttons(btn_guardar, btn_cancelar, dlg);
                } catch (e) {
                    show_error(e);
                    enable_buttons(btn_guardar, btn_cancelar, dlg);
                }
            }
        });
    }

    function caja_eliminar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            if (row.descripcion === 'FONDO DE CAJA') {
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
                        if (result.success) {
                            dg.datagrid('loadData', {total: 0, rows: [], footer: []});
                            dg.datagrid('getPanel').panel('setTitle', 'Abrir caja');
                            caja_check_exist();
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
        });
    }

    function typeFormatter(value) {
        value = Number(value);
        if (value === 1) {
            value = '<span class="badge badge-pill badge-success">INGRESO</span>';
        } else if (value === 2) {
            value = '<span class="badge badge-pill badge-danger">EGRESO</span>';
        } else {
            value = '';
        }
        return value;
    }

    function footerFormatter(value) {
        if (value === 'Total Ingresos') {
            value = `<span class="text-success float-right">${value}</span>`;
        } else if (value === 'Total Egresos') {
            value = `<span class="text-danger float-right">${value}</span>`;
        } else if (value === 'Total Efectivo') {
            value = `<span class="float-right">${value}</span>`;
        }
        return value;
    }

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

    txt_saldoinicial.numberbox({
        width: '100%',
        label: 'Fondo de caja',
        labelPosition: 'top',
        mi: 0,
        precision: 2,
        required: true
    });

    function abrir_caja() {
        fmab.form('clear');
        dlgab.dialog('open').dialog('setTitle', 'Abrir Caja');
        route = 'caja/add';
    }

    function caja_abrir() {
        fmab.form('submit', {
            url: route,
            onSubmit: function () {
                let valid = fmab.form('validate');
                if (valid) {
                    disable_buttons(btn_abrir_guardar, btn_abrir_cancelar, dlgab);
                }
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    dlgab.dialog('close');
                    caja_check_exist();
                    show_info(result.msg);
                    enable_buttons(btn_abrir_guardar, btn_abrir_cancelar, dlgab);
                } else {
                    show_error(result.msg);
                    enable_buttons(btn_abrir_guardar, btn_abrir_cancelar, dlgab);
                }
            }
        });
    }

    function disable_buttons(btnOk, btnCancel, dialog) {
        btnCancel.linkbutton('disable');
        btnOk.linkbutton('disable');
        btnOk.linkbutton({text: 'Guardando...'});
        dialog.dialog('showMask', 'Procesando...');
    }

    function enable_buttons(btnOk, btnCancel, dialog) {
        btnCancel.linkbutton('enable');
        btnOk.linkbutton('enable');
        btnOk.linkbutton({text: 'Guardar'});
        dialog.dialog('hideMask');
    }

    return {
        init: init
    };

})();
app.caja.init();