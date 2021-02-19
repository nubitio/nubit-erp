var app = app || {};
app.marcador = (function () {
    let win = $('#marcador_window');
    let fm = $('#marcador_fm');
    let lbl_clock = $('#marcador_clock');
    let txt_dni = $('#marcador_dni');
    let btn_registrar = $('#marcador_registrar');

    let init = function () {
        win.window({
            title: '',
            width: 400,
            height: 320,
            border: false,
            shadow: false,
            draggable: false,
            resizable: false,
            collapsible: false,
            minimizable: false,
            maximizable: false,
            closable: false,
            cls: 'login-window'
        });

        txt_dni.textbox({
            width: '100%',
            prompt: 'DNI',
            label: 'DNI',
            labelPosition: 'top',
            iconCls: 'fas fa-card-id fa-1x'
        });

        txt_dni.textbox('textbox').focus();

        txt_dni.textbox('textbox').bind('keydown', function (e) {
            if (e.keyCode === 13) {
                registrar();
            }
        });

        btn_registrar.linkbutton({
            width: '100%',
            height: 40
        });

        $.get('marcador/time')
            .done(function (response) {
                lbl_clock.html(response);
            })
            .fail(function (xhr, error, status) {
                show_error(error);
            });

        setInterval(function () {
            $.get('marcador/time')
                .done(function (response) {
                    lbl_clock.html(response);
                })
                .fail(function (xhr, error, status) {
                    show_error(error);
                });
        }, 10000);
    };

    let registrar = function () {
        fm.form('submit', {
            url: 'marcador/registrar',
            onSubmit: function () {
                let valid = $(this).form('validate');
                if (valid) {
                    btn_registrar.linkbutton('disable');
                    btn_registrar.linkbutton({text: 'Registrando...'});
                }
                return valid;
            },
            success: function (result) {
                result = JSON.parse(result);
                if (result.success) {
                    txt_dni.textbox('clear');
                    btn_registrar.linkbutton('enable');
                    btn_registrar.linkbutton({text: 'Registrar'});
                    show_info(result.msg);

                } else {
                    btn_registrar.linkbutton('enable');
                    btn_registrar.linkbutton({text: 'Registrar'});
                    show_error(result.msg);
                }
            }
        });
    };

    return {
        init: init,
        registrar: registrar
    };
})();
app.marcador.init();