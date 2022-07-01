var app = app || {};
app.rol = (function () {
    const ROUTES = Object.freeze({
        ADD: 'rol/add/',
        EDIT: 'rol/edit/',
        DELETE: 'rol/delete/',
    });

    let dataGrid = null;
    let dialog = null;
    let form = null;

    let rol_id;
    let rm_tg = $('#rol_menu_tg');
    let rm_dlg = $('#rol_menu_dlg');
    let rm_btn_guardar;
    let rm_btn_cancelar;

    let init = function () {
        let fields = [
            {
                type: window.FormType.HIDDEN,
                name: 'rol_id',
                isKey: true,
                visible: false
            },
            {
                type: window.FormType.HIDDEN,
                label: 'Codigo',
                name: 'rol_id',
                visible: true
            },
            {
                type: window.FormType.TEXT,
                label: 'Descripcion',
                name: 'descripcion',
                visible: true,
                required: true,
                width: 200
            },
        ];

        dataGrid = new window.DataGrid('wrapper', fields, {
            title: 'Roles',
            url: 'rol/get',
            routes: ROUTES,
        });

        dialog = new window.Dialog('wrapper');
        form = new window.Form(dialog.id, fields);

        //AGREGA BOTTON PERMISO//
        let toolbarButtons=[
            {text: 'Permisos',
                iconCls: 'fas fa-user-shield fa-1x',
                handler: function () {
                    permisos()
                }
            }
        ];
        dataGrid.addToolbarButtons(toolbarButtons);

        //DIALOGO DE PERMISO GUARDAR Y CANCELAR//
        rm_dlg.dialog({
            height: 500,
            buttons: [{
                id: 'rol_menu_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    rm_dlg.dialog('close');
                }
            }, {
                id: 'rol_menu_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    rm_guardar();
                }
            }],
            onOpen: function () {
                rm_dlg.dialog('center');
                rm_dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                rm_btn_guardar = $('#rol_menu_guardar');
                rm_btn_cancelar = $('#rol_menu_cancelar');
                rm_btn_guardar.addClass('md-btn');
                rm_btn_cancelar.addClass('md-btn-plain');
            }
        });

        //DIALOGO CHECKBOX//
        rm_tg.tree({
            checkbox: true,
            lines: true
        });
    };

    function permisos() {
        let row = dataGrid.selectedRow;
        if (row) {
            rol_id = row.rol_id;
            rm_tg.tree('loadData', []);
            rm_dlg.dialog('open').dialog('setTitle', 'Permisos (' + row.descripcion + ')');
            rm_dlg.dialog('showMask', 'Cargando...');
            $.post('rol/getmr', {rol_id: rol_id})
                .done(function (response) {
                    rm_tg.tree('loadData', response);
                    rm_dlg.dialog('hideMask');
                })
                .fail(function (xhr, status, error) {
                    show_error(error);
                    rm_dlg.dialog('hideMask');
                });
        } else {
            show_info('Seleccione un registro');
        }
    }

    function rm_guardar() {
        let selected = rm_tg.tree('getChecked', ['checked', 'indeterminate']);
        let permisos = selected.map(function (obj) {
            return obj.id;
        });
        rm_disable_buttons();
        $.post('rol/setmr/' + rol_id, {permisos: permisos})
            .done(function (response) {
                if (response.success) {
                    rm_dlg.dialog('close');
                    show_info(response.msg)
                } else {
                    show_error(response.msg);
                }
                rm_enable_buttons();
            })
            .fail(function (xhr, status, error) {
                show_error(error);
                rm_enable_buttons();
            });
    }

    function rm_disable_buttons() {
        rm_btn_cancelar.linkbutton('disable');
        rm_btn_guardar.linkbutton('disable');
        rm_btn_guardar.linkbutton({text: 'Guardando...'});
        rm_dlg.dialog('showMask', 'Procesando...');
    }

    function rm_enable_buttons() {
        rm_btn_cancelar.linkbutton('enable');
        rm_btn_guardar.linkbutton('enable');
        rm_btn_guardar.linkbutton({text: 'Guardar'});
        rm_dlg.dialog('hideMask');
    }

    return {
        init: init
    };
})();
app.rol.init();