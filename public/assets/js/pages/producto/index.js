var app = app || {};
app.producto = (function () {
    let route;
    let dg = $('#producto_dg');
    let dlg = $('#producto_dlg');
    let fm = $('#producto_fm');
    let txt_descripcion = $('#producto_descripcion');
    let cbo_categoria = $('#producto_categoria');
    let txt_precio = $('#producto_precio');
    let cbo_estado = $('#producto_estado');
    let btn_guardar;
    let btn_cancelar;

    let init = function () {
        dg.datagrid({
            title: 'Productos',
            url: 'producto/get',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                },
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    editar();
                },
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    eliminar();
                },
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
                field: 'precio',
                title: 'Precio',
                sortable: true,
                width: 80,
                align: 'right'
            }, {
                field: 'estado',
                title: 'Activo',
                sortable: true,
                align: 'center',
                formatter: activeFormatter,
            }]],
            onDblClickRow: function () {
                editar();
            }
        });

        dg.datagrid('enableFilter', []);

        dlg.dialog({
            buttons: [{
                id: 'producto_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'producto_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onOpen: function () {
                dlg.dialog('center');
                $(this).parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#producto_guardar');
                btn_cancelar = $('#producto_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
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
            url: 'categoria/lista/1',
            method: 'get',
            valueField: 'categoria_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_precio.numberbox({
            width: '100%',
            label: 'Precio',
            labelPosition: 'top',
            min: 0,
            precision: 2,
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
            editable: false,
            required: true
        });
    };

    function nuevo() {
        fm.form('clear');
        route = 'producto/add';
        cbo_estado.combobox('select', 1);
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
    }

    function editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'producto/edit/' + row.producto_id;
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
        let row = dg.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', '¿Está seguro que desea eliminar este registro?', function (r) {
                if (r) {
                    $.post('producto/delete/' + row.producto_id)
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
        window.open('producto/exportar');
    }

    return {
        init: init
    };
})();
app.producto.init();
