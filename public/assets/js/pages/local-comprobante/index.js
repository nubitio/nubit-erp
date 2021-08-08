var app = app || {};
app.local_comprobante = (function () {
    let route;
    let dg = $('#local_comprobante_dg');
    let dlg = $('#local_comprobante_dlg');
    let fm = $('#local_comprobante_fm');

    let cbo_empresa = $('#local_comprobante_empresa');
    let cbo_local = $('#local_comprobante_local');
    let cbo_tipo = $('#local_comprobante_tipo');
    let txt_serie = $('#local_comprobante_serie');
    let txt_serie_asociada = $('#local_comprobante_serie_asociada');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Local comprobante',
            url: 'local-comprobante/get',
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
            }],
            pagination: PAGINATION,
            pageSize: PAGE_SIZE,
            striped: STRIPED,
            rownumbers: ROW_NUMBERS,
            fitColumns: true,
            fit: true,
            border: BORDER,
            singleSelect: SINGLE_SELECT,
            multiSort: true,
            columns: [[{
                field: 'empresa',
                title: 'Empresa',
                sortable: true,
                width: 200
            }, {
                field: 'local',
                title: 'Local',
                sortable: true,
                width: 200
            }, {
                field: 'tipo_comprobante',
                title: 'Tipo',
                sortable: true,
                width: 200
            }, {
                field: 'serie',
                title: 'Serie',
                sortable: true,
                width: 100
            }, {
                field: 'serie_asociada',
                title: 'Serie asociada',
                sortable: true,
                width: 100
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'empresa',
            type: 'combobox',
            options: {
                url: 'empresa/lista/1/1',
                method: 'get',
                valueField: 'empresa_id',
                textField: 'razon_social',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'empresa_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'empresa_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'local',
            type: 'combobox',
            options: {
                url: 'local/lista/1/1',
                method: 'get',
                valueField: 'local_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'local_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'local_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'tipo_comprobante',
            type: 'combobox',
            options: {
                url: 'comprobante/lista/1/1',
                method: 'get',
                valueField: 'tipo_comprobante_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'tipo_comprobante_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'tipo_comprobante_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        dlg.dialog({
            width: DIALOG_WIDTH,
            height: DIALOG_HEIGHT,
            modal: MODAL,
            closed: CLOSED,
            inline: INLINE,
            shadow: SHADOW,
            closable: false,
            border: 'thin',
            cls: 'fondo-blue-dark',
            buttons: [{
                id: 'local_comprobante_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'local_comprobante_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#local_comprobante_guardar');
                btn_cancelar = $('#local_comprobante_cancelar');
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

        cbo_tipo.combobox({
            width: '100%',
            label: 'Tipo',
            labelPosition: 'top',
            url: 'comprobante/lista/1/0',
            method: 'get',
            valueField: 'codigo',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_serie.textbox({
            width: '100%',
            label: 'Serie',
            labelPosition: 'top',
            required: true
        });

        txt_serie_asociada.textbox({
            width: '100%',
            label: 'Serie asociada',
            labelPosition: 'top',
            required: false
        });
    };

    //funciones

    function nuevo() {
        fm.form('clear');
        route = 'local-comprobante/add';
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'local-comprobante/edit/' + row.local_id + '/' + row.tipo_comprobante_id + '/' + row.serie;
        } else {
            show_info('Seleccione un registro');
        }
    }

    function guardar() {
        btn_guardar = $('#local_comprobante_guardar');
        btn_cancelar = $('#local_comprobante_cancelar');
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
                        show_info('Ya existe el local_comprobante Ingresado ...');
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
        let row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('local_comprobante/delete/' + row.local_id + '/' + row.tipo_comprobante_id + '/' + row.serie)
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

    return {
        init: init
    };
})();
app.local_comprobante.init();
