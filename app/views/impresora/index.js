var app = app || {};
app.impresora = (function () {
    var route;
    var dg = $('#impresora_dg');
    var dlg = $('#impresora_dlg');
    var fm = $('#impresora_fm');
    var cbo_empresa = $('#impresora_empresa');
    var cbo_local = $('#impresora_local');
    var txt_marca = $('#impresora_marca');
    var txt_modelo = $('#impresora_modelo');
    var txt_serie = $('#impresora_serie');
    var cbo_estado = $('#impresora_estado');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        dg.datagrid({
            title: 'Impresoras',
            url: 'impresora/get',
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
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[{
                field: 'marca',
                title: 'Marca',
                sortable: true,
                width: 200
            }, {
                field: 'modelo',
                title: 'Modelo',
                sortable: true,
                width: 200
            }, {
                field: 'serie',
                title: 'Serie',
                sortable: true,
                width: 300
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 100,
                align: 'center',
                formatter: estado_formatter,
                styler: estado_styler
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'estado',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'ACTIVO'
                }, {
                    value: '2',
                    text: 'INACTIVO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'estado');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'estado',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        dlg.dialog({
            buttons: [{
                id: 'impresora_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'impresora_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#impresora_guardar');
                btn_cancelar = $('#impresora_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        cbo_empresa.combobox({
            width: '100%',
            label: 'Empresa',
            labelPosition: 'top',
            url: 'empresa/lista/1/0',
            method: 'get',
            valueField: 'empresa_id',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            onSelect: function (rec) {
                cbo_local.combobox('reload', 'local/cargar/' + rec.empresa_id);
            },
            required: true
        });

        cbo_local.combobox({
            width: '100%',
            label: 'Local',
            labelPosition: 'top',
            method: 'get',
            valueField: 'local_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_marca.textbox({
            width: '100%',
            label: 'Marca',
            labelPosition: 'top',
            required: true
        });

        txt_modelo.textbox({
            width: '100%',
            label: 'Modelo',
            labelPosition: 'top',
            required: true
        });

        txt_serie.textbox({
            width: '100%',
            label: 'Serie',
            labelPosition: 'top'
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
            editable: false,
            required: true
        });
    };

//funciones
    function nuevo() {
        fm.form('clear');
        route = 'impresora/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'impresora/edit/' + row.impresora_id;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
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

    function eliminar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('impresora/delete/' + row.impresora_id)
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

    function exportar() {
        window.open('impresora/exportar');
    }

    return {
        init: init
    };
})();
app.impresora.init();