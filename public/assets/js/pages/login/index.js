var app = app || {};
app.login = (function () {
    let fm = $('#login_fm');
    let txt_username = $('#username');
    let txt_password = $('#password');
    let btn_login = $('#login');
    //local
    let dlg = $('#home_local');
    let lista_local = $('#home_lista_local');
    let btn_guardar;
    let btn_cancelar;

    window.onload = function () {
        $('.wrapper').animate({"opacity": 1});
    }

    let init = function () {
        localStorage.removeItem('session');

        dlg.dialog({
            title: 'Seleccione un local',
            border: false,
            width: 400,
            height: 250,
            modal: true,
            closed: true,
            inline: false,
            shadow: false,
            draggable: false,
            resizable: false,
            collapsible: false,
            minimizable: false,
            maximizable: false,
            closable: false,
            buttons: [{
                id: 'home_cancelar',
                text: 'Cancelar',
                handler: function () {
                    dlg.dialog('close');
                    window.location = ('logout');
                }
            }, {
                id: 'home_guardar',
                text: 'Continuar',
                handler: function () {
                    set_local();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                $(this).parent().animateCss(WINDOW_OPEN_ANIMATION);
                let btn_save = $('#home_guardar');
                let btn_cancel = $('#home_cancelar');
                btn_save.addClass('md-btn');
                btn_cancel.addClass('md-btn-plain');
            }
        });

        txt_username.textbox({
            width: '100%',
            prompt: 'Usuario',
            label: 'Usuario',
            labelPosition: 'top',
            iconCls: 'fas fa-user fa-1x'
        });

        txt_username.textbox('textbox').focus();

        txt_password.passwordbox({
            width: '100%',
            prompt: 'Contraseña',
            label: 'Contraseña',
            labelPosition: 'top',
            iconWidth: 25,
            lastDelay: 0,
            checkInterval: 0
        });

        txt_password.passwordbox('textbox').bind('keydown', function (e) {
            if (e.keyCode === 13) {
                login();
            }
        });

        btn_login.linkbutton({
            width: '100%',
            height: 40
        });

        lista_local.datalist({
            method: 'get',
            fit: true,
            valueField: 'local_id',
            textField: 'descripcion',
            lines: true
        });
    };

    let login = function login() {
        fm.form('submit', {
            url: 'autenticar',
            onSubmit: function () {
                let valid = $(this).form('validate');
                if (valid) {
                    btn_login.linkbutton('disable');
                    btn_login.linkbutton({text: 'Autenticando...'});
                }
                return valid;
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    dlg.dialog('open');
                    lista_local.datalist('load', 'local/lista/1/0');
                    show_info("Seleccione un local para continuar");
                } else {
                    btn_login.linkbutton('enable');
                    btn_login.linkbutton({text: 'Iniciar sesión'});
                    show_error(result.msg);
                }
            }
        });
    };

    function set_local() {
        let row = lista_local.datalist('getSelected');
        if (row) {
            btn_guardar = $('#home_guardar');
            btn_cancelar = $('#home_cancelar');
            disable_buttons();
            $.get('home/set-local/' + row.local_id)
                .done(function (response) {
                    enable_buttons();
                    if (response.success) {
                        dlg.dialog('close');
                        btn_login.linkbutton({text: 'Autenticado'});
                        show_info("Bienvenido!");
                        window.location.href = "home";
                        localStorage.setItem('session', JSON.stringify(response.data));
                    }
                })
                .fail(function (xhr, status, error) {
                    show_error(error);
                    enable_buttons()
                });
        } else {
            show_info('Seleccione un local');
        }
    }

    function disable_buttons() {
        btn_cancelar.linkbutton('disable');
        btn_guardar.linkbutton('disable');
        btn_guardar.linkbutton({text: 'Procesando...'});
        dlg.dialog('showMask', 'Procesando...');
    }

    function enable_buttons() {
        btn_cancelar.linkbutton('enable');
        btn_guardar.linkbutton('enable');
        btn_guardar.linkbutton({text: 'Aceptar'});
        dlg.dialog('hideMask');
    }

    return {
        init: init,
        login: login,
    };
})();
app.login.init();