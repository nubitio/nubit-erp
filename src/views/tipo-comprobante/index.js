var app = app || {};
app.comprobante = (function () {
    let route;
    let dg = $('#comprobante_dg');
    let dlg = $('#comprobante_dlg');
    let fm = $('#comprobante_fm');
    let txt_codigo = $('#comprobante_codigo');
    let txt_descripcion = $('#comprobante_descripcion');
    let cbo_estado = $('#comprobante_estado');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Tipos de comprobante',
            url: 'comprobante/get',
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
                field: 'codigo',
                title: 'Codigo',
                sortable: true,
                align: 'center'
            }, {
                field: 'descripcion',
                title: 'Descripcion',
                sortable: true,
                width: 400
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
                id: 'comprobante_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'comprobante_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#comprobante_guardar');
                btn_cancelar = $('#comprobante_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        txt_codigo.textbox({
            width: '100%',
            label: 'Codigo',
            labelPosition: 'top',
            required: true
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripcion',
            labelPosition: 'top',
            required: true
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
    };

    // functions
    function nuevo() {
        fm.form('clear');
        route = 'comprobante/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'comprobante/edit/' + row.tipo_comprobante_id;
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
                        show_info('Ya existe el número de Ingresado ...');
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
                    $.post('comprobante/delete/' + row.tipo_comprobante_id)
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
        window.open('comprobante/exportar');
    }

    return {
        init: init
    };

})();
app.comprobante.init();
