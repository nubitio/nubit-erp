var app = app || {};
app.home = (function () {
    let dlg = $('#home_local');
    let lista_local = $('#home_lista_local');
    var lbl_local = $('#home_local_title');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dlg.dialog({
            title: 'Seleccione un local',
            border: 'thin',
            cls: 'fondo-blue-dark',
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

        lista_local.datalist({
            method: 'get',
            url: 'local/lista/1/0',
            fit: true,
            valueField: 'local_id',
            textField: 'descripcion',
            lines: true
        });
    };

    let change_local = function () {
        dlg.dialog('open');
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
                        lbl_local.html(row.descripcion);
                        load_page('dashboard', 'dashboard');
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
        change_local: change_local
    };
})();
app.home.init();