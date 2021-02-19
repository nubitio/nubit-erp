var url;
var txt_actual = $('#pwd_actual');
var txt_nueva = $('#pwd_nueva');
var txt_confirmar = $('#pwd_confirmar');
var dlg = $('#cpassword_dlg');
// dialog
dlg.dialog({
    title: 'Cambiar Contraseña',
    width: DIALOG_WIDTH,
    height: DIALOG_HEIGHT,
    modal: false,
    closed: false,
    draggable: false,
    closable: false,
    inline: INLINE,
    shadow: SHADOW,
    buttons: [{
        id: 'categoria_cancelar',
        text: TEXT_CANCEL,
        iconCls: ICON_CANCEL,
        handler: function () {
            $('#tt').tabs('close', 'Cambiar Contraseña');
        }
    }, {
        id: 'categoria_guardar',
        text: TEXT_SAVE,
        iconCls: ICON_SAVE,
        handler: function () {
            cpassword_guardar();
        }
    }],
    onOpen: function () {
        $(this).parent().animateCss(DIALOG_OPEN_ANIMATION);
    }
});
// bind
txt_actual.passwordbox({
    prompt: 'Contraseña actual',
    iconWidth: 25,
    required: true
});

txt_nueva.passwordbox({
    prompt: 'Nueva contraseña',
    iconWidth: 25,
    required: true
});

txt_confirmar.passwordbox({
    prompt: 'Confirmar contraseña',
    iconWidth: 25,
    validType: 'confirmPass["#pwd_nueva"]',
    required: true
});

function cpassword_guardar() {
    $('#cpassword_fm').form('submit', {
        url: 'spassword',
        onSubmit: function () {
            valid = $(this).form('validate');
            if (valid) {
                $('#categoria_cancelar').linkbutton('disable');
                $('#categoria_guardar').linkbutton('disable');
                $('#categoria_guardar').linkbutton({text: 'Guardando...'});
            }
            return valid;
        },
        success: function (result) {
            result = JSON.parse(result);
            if (result.success) {
                $.messager.show({
                    title: 'Info',
                    msg: result.msg
                });
                $('#cpassword_dlg').dialog('close');
                $('#tt').tabs('close', 'Cambiar Contraseña');
            } else {
                $('#categoria_cancelar').linkbutton('enable');
                $('#categoria_guardar').linkbutton('enable');
                $('#categoria_guardar').linkbutton({text: 'Guardar'});
                $.messager.show({
                    title: 'Error',
                    msg: result.msg
                });
            }
        }
    });
}
