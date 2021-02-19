var app = app || {};
app.producto = (function () {
    var route;
    var dg = $('#producto_dg');
    var dlg = $('#producto_dlg');
    var fm = $('#producto_fm');
    var txt_descripcion = $('#producto_descripcion');
    var cbo_categoria = $('#producto_categoria');
    var cbo_destino = $('#producto_destino');
    var txt_precio = $('#producto_precio');
    var cbo_estado = $('#producto_estado');
    var btn_guardar;
    var btn_cancelar;

    var precio_local = $('#producto_precio_local');

    var session;

    var init = function () {
        session = JSON.parse(localStorage.getItem('session'));

        var dgurl = 'producto/get';
        var isdisabled = true;

        if (session.rol_id == null || session.rol_id === 1) {
            dgurl = 'producto/getadm';
            isdisabled = false;
        }

        dg.datagrid({
            title: 'Productos',
            url: dgurl,
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                },
                disabled: isdisabled
            }, '-', {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    editar();
                },
                disabled: isdisabled
            }, '-', {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    eliminar();
                },
                disabled: isdisabled
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
                field: 'destino',
                title: 'Destino',
                sortable: true,
                width: 150
            }, {
                field: 'precio',
                title: 'Precio',
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
            field: 'destino',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODOS'
                }, {
                    value: '1',
                    text: 'COCINA'
                }, {
                    value: '2',
                    text: 'BARRA'
                }, {
                    value: '3',
                    text: 'COCINA/BARRA'
                }, {
                    value: '4',
                    text: 'VITRINA'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'destino_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'destino_id',
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

        if (session.rol_id == null || session.rol_id == 1) {
            dg.datagrid('hideColumn', 'precio');
        }

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
            url: 'categoria/lista/1/0',
            method: 'get',
            valueField: 'categoria_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        cbo_destino.combobox({
            width: '100%',
            label: 'Destino',
            labelPosition: 'top',
            data: [{
                value: '1',
                text: 'COCINA'
            }, {
                value: '2',
                text: 'BARRA'
            }, {
                value: '3',
                text: 'COCINA/BARRA'
            }, {
                value: '4',
                text: 'VITRINA'
            }],
            panelHeight: 'auto',
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
        var row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'producto/edit/' + row.producto_id;
            //load_precios(row.producto_id);
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

    function load_precios(id) {
        dlg.dialog('showMask', 'Cargando...');
        //var template = list_template.html();
        $.get('producto/precios/' + id)
            .done(function (result) {
                precio_local.empty();
                dlg.dialog('hideMask');
                for (let i = 0; i < result.length; i++) {
                    precio_local.append(`<tr><td><input type="checkbox"></td><td>${result[i].local}</td><td><input type="text" class="precio" value="${result[i].precio}"></td></tr>`)
                }
                $('.precio').numberbox({});
            })
            .fail(function (xhr, status, error) {
                show_error(error);
                dlg.dialog('hideMask');
            });
    }

    return {
        init: init
    };
})();
app.producto.init();
