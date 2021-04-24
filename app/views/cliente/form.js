var app = app || {};
app.customerForm = (function () {
    let route;
    let dlg = $('#cliente_dlg');
    let fm = $('#cliente_fm');
    let txt_ruc = $('#cliente_ruc');
    let txt_razon_social = $('#cliente_razon_social');
    let txt_domicilio_fiscal = $('#cliente_domicilio_fiscal');
    let txt_telefono = $('#cliente_telefono');
    let txt_email = $('#cliente_email');
    let cbo_estado = $('#cliente_estado');

    let btnSave;
    let btnCancel;

    let enter_pressed = false;

    let init = function () {
        dlg.dialog({
            buttons: [{
                id: 'cliente_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'cliente_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btnSave = $('#cliente_guardar');
                btnCancel = $('#cliente_cancelar');
                btnSave.addClass('md-btn');
                btnCancel.addClass('md-btn-plain');
            }
        });

        txt_ruc.textbox({
            width: '100%',
            label: 'RUC/DNI',
            labelPosition: 'top',
            min: 0,
            validType: 'length[8,11]',
            invalidMessage: 'Ingrese un RUC/DNI valido.',
            icons: [{
                iconCls: ICON_SEACH,
                handler: function (e) {
                    consult()
                }
            }],
            required: true
        });

        txt_ruc.textbox('textbox').attr('maxlength', '11');
        txt_ruc.textbox('textbox').attr('pattern', '[0-9]');

        txt_razon_social.textbox({
            width: '100%',
            height: 80,
            label: 'Cliente',
            labelPosition: 'top',
            multiline: true,
            required: true
        });

        txt_domicilio_fiscal.textbox({
            width: '100%',
            height: 80,
            label: 'Domicilio',
            labelPosition: 'top',
            multiline: true
        });

        txt_telefono.numberbox({
            width: '100%',
            label: 'Telefono',
            labelPosition: 'top',
            validType: 'length[6,15]'
        });

        txt_email.textbox({
            width: '100%',
            label: 'Email',
            labelPosition: 'top',
            validType: 'email'
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
            required: true
        });

        txt_ruc.textbox('textbox').bind('keydown', function (e) {
            if (e.keyCode === 13 && !enter_pressed) {
                enter_pressed = true;
                consult()
            }
        });
    }

    function consult() {
        let doc = txt_ruc.textbox('getValue');
        let apiUrl = ''
        let dialogTitle = ''
        if (doc.length === 11) {
            dialogTitle = 'Consultando RUC';
            apiUrl = 'api/ruc/';
        } else {
            dialogTitle = 'Consultando DNI';
            apiUrl = 'api/dni/'
        }
        dlg.dialog('showMask', dialogTitle);
        $.get(apiUrl + txt_ruc.textbox('getValue'))
            .done(function (result) {
                enter_pressed = false;
                if (result.error) {
                    show_error(result.error);
                } else {
                    if (doc.length === 11) {
                        if (result.estado === 'ACTIVO') {
                            txt_razon_social.textbox('setValue', result.razonSocial);
                            txt_domicilio_fiscal.textbox('setValue', result.direccion);
                        } else {
                            show_error(result.razonSocial + '<br>' + result.estado);
                        }
                    } else {
                        txt_razon_social.textbox('setValue', result.nombres + ' ' + result.apellidoPaterno + ' ' + result.apellidoMaterno);
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

    function add() {
        fm.form('clear');
        route = 'cliente/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function edit() {
        let row = app.customer.dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'cliente/edit/' + row.ruc;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function erase() {
        let row = app.customer.dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', TEXT_CONFIRM, function (r) {
                if (r) {
                    $.post('cliente/delete/' + row.ruc)
                        .done(function (result) {
                            if (result.success) {
                                app.customer.dg.datagrid('reload');
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

    function save() {
        fm.form('submit', {
            url: route,
            onSubmit: function () {
                let valid = fm.form('validate');
                if (valid) {
                    disableButtons();
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        dlg.dialog('close');
                        show_info(result.msg);
                        mainObserver.notify('customer:save', result)
                    } else {
                        show_error(result.msg);
                    }
                    enableButtons();
                } catch (e) {
                    show_error(e);
                    enableButtons();
                }
            }
        });
    }

    function disableButtons() {
        btnCancel.linkbutton('disable');
        btnSave.linkbutton('disable');
        btnSave.linkbutton({text: 'Guardando...'});
        dlg.dialog('showMask', 'Procesando...');
    }

    function enableButtons() {
        btnCancel.linkbutton('enable');
        btnSave.linkbutton('enable');
        btnSave.linkbutton({text: 'Guardar'});
        dlg.dialog('hideMask');
    }

    return {
        init: init,
        add: add,
        edit: edit,
        erase: erase
    }
})();

app.customerForm.init()
