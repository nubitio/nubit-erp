var app = app || {};
app.insumo = (function () {
    var route;
    var dg = $('#insumo_dg');
    var dlg = $('#insumo_dlg');
    var fm = $('#insumo_fm');
    var txt_descripcion = $('#insumo_descripcion');
    var cbo_categoria = $('#insumo_categoria');
    var cbo_unidad = $('#insumo_unidad');
    var txt_stock_minimo = $('#insumo_stock_minimo');
    var txt_precio_compra = $('#insumo_precio_compra');
    var cbo_estado = $('#insumo_estado');
    var btn_guardar;
    var btn_cancelar;

    var init = function () {
        dg.datagrid({
            title: 'Insumos',
            url: 'insumo/get',
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
                field: 'descripcion',
                title: 'Descripción',
                sortable: true,
                width: 300
            }, {
                field: 'categoria',
                title: 'Categoria',
                sortable: true,
                width: 180
            }, {
                field: 'unidad',
                title: 'Unidad',
                sortable: true,
                width: 180
            }, {
                field: 'stock_minimo',
                title: 'Stock minimo',
                sortable: true,
                width: 80,
                align: 'right'
            }, {
                field: 'stock',
                title: 'Stock',
                sortable: true,
                width: 80,
                align: 'right'
            }, {
                field: 'estado',
                title: 'Estado',
                sortable: true,
                width: 120,
                align: 'center',
                formatter: estado_formatter,
                styler: estado_styler
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'unidad',
            type: 'combobox',
            options: {
                url: 'unidad/lista/1/1',
                method: 'get',
                valueField: 'unidad_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'unidad_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'unidad_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'categoria',
            type: 'combobox',
            options: {
                url: 'categoria/lista/1/1',
                method: 'get',
                valueField: 'categoria_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'categoria_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'categoria_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
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
                id: 'insumo_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'insumo_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#insumo_guardar');
                btn_cancelar = $('#insumo_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
                console.log({btn_cancelar, btn_guardar});
            }
        });

        txt_descripcion.textbox({
            width: '100%',
            label: 'Descripción',
            labelPosition: 'top',
            required: true
        });

        cbo_categoria.combobox({
            width: '100%',
            label: 'Categoria',
            labelPosition: 'top',
            url: 'categoria/lista/1/0',
            method: 'get',
            valueField: 'categoria_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        cbo_unidad.combobox({
            width: '100%',
            label: 'Unidad',
            labelPosition: 'top',
            url: 'unidad/lista/1/0',
            method: 'get',
            valueField: 'unidad_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true
        });

        txt_stock_minimo.numberbox({
            width: '100%',
            label: 'Stock mínimo',
            labelPosition: 'top',
            min: 0,
            precision: 2
        });

        txt_precio_compra.numberbox({
            width: '100%',
            label: 'Precio De Compra',
            labelPosition: 'top',
            min: 0,
            precision: 2
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

    function nuevo() {
        fm.form('clear');
        route = 'insumo/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'insumo/edit/' + row.producto_id;
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
                    $.post('insumo/delete/' + row.producto_id)
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
        window.open('insumo/exportar');
    }

    return {
        init: init
    };
})();
app.insumo.init();
