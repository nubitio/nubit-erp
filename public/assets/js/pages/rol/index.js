var app = app || {};
app.rol = (function () {
    let route;
    let dg = $('#rol_dg');
    let dlg = $('#rol_dlg');
    let fm = $('#rol_fm');
    let txt_descripcion = $('#rol_descripcion');
    let btn_guardar;
    let btn_cancelar;

    let rol_id;
    let rm_tg = $('#rol_menu_tg');
    let rm_dlg = $('#rol_menu_dlg');
    let rm_btn_guardar;
    let rm_btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Roles',
            url: 'rol/get',
            remoteFilter: true,
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
                text: 'Permisos',
                iconCls: 'fas fa-user-shield fa-1x',
                handler: function () {
                    permisos();
                }
            }],
            columns: [[{
                field: 'rol_id',
                title: 'Codigo',
                sortable: true,
            }, {
                field: 'descripcion',
                title: 'Descripcion',
                sortable: true,
                width: 100
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter');

        dlg.dialog({
            buttons: [{
                id: 'rol_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'rol_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#rol_guardar');
                btn_cancelar = $('#rol_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

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

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
        });

        rm_tg.tree({
            checkbox: true,
            lines: true
        });
    };

    function nuevo() {
        fm.form('clear');
        route = 'rol/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'rol/edit/' + row.rol_id;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        fm.form('submit', {
            url: route,
            onSubmit: function () {
                let valid = fm.form('validate');
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

    function eliminar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('rol/delete/' + row.rol_id)
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

    function permisos() {
        let row = dg.datagrid('getSelected');
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