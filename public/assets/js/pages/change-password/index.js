var app = app || {};
app.password = (function () {
    let dlg = $('#dlg');
    let fm = $('#fm');
    let txtActualPassword = $('#actualPassword');
    let txtNewPassword = $('#newPassword');
    let txtConfirmPassword = $('#confirmPassword');
    let btnSave;
    let btnCancel;

    let init = function () {
        dlg.dialog({
            title: 'Cambiar Contraseña',
            buttons: [{
                id: 'password_cancel',
                text: TEXT_CANCEL,
                handler: function () {
                    load_page('dashboard', 'Dashboard');
                }
            }, {
                id: 'password_save',
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btnSave = $('#password_save');
                btnCancel = $('#password_cancel');
                btnSave.addClass('md-btn');
                btnCancel.addClass('md-btn-plain');
            }
        });

        txtActualPassword.passwordbox({
            width: '100%',
            label: 'Contraseña actual',
            labelPosition: 'top',
            iconWidth: 25,
            required: true
        });

        txtNewPassword.passwordbox({
            width: '100%',
            label: 'Nueva contraseña',
            labelPosition: 'top',
            iconWidth: 25,
            required: true
        });

        txtConfirmPassword.passwordbox({
            width: '100%',
            label: 'Confirmar contraseña',
            labelPosition: 'top',
            iconWidth: 25,
            validType: 'confirmPass["#newPassword"]',
            required: true
        });

        dlg.dialog('open');
    }

    function save() {
        fm.form('submit', {
            url: 'password/update',
            onSubmit: function () {
                let valid = $(this).form('validate');
                if (valid) {
                    disableButtons(btnSave, btnCancel, dlg)
                }
                return valid;
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    show_info(result.msg)
                    dlg.dialog('close');
                    window.location.href = '/logout'
                } else {
                    enableButtons(btnSave, btnCancel, dlg)
                    show_error(result.msg)
                }
            }
        });
    }

    return {
        init: init
    }
})();
app.password.init();
