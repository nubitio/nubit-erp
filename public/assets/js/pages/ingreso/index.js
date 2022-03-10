var app = app || {};
app.ingreso = (function () {
    let route;
    let position = 1;
    let lista_operaciones;
    let lista_almacenes;
    let lista_productos;
    let dg = $('#ingreso_dg');
    let dlg = $('#ingreso_dlg');
    let fm = $('#ingreso_fm');
    let db_fecha = $('#ingreso_fecha');
    let cbo_operacion = $('#ingreso_operacion');
    let cbo_almacen = $('#ingreso_almacen');
    let cbo_proveedor = $('#ingreso_proveedor');
    let txt_glosa = $('#ingreso_glosa');
    let btn_add = $('#ingreso_add');
    let tb_detalle = $('#ingreso_tb_detalle');
    let list_template = $('#ingreso_template');
    let btn_guardar;
    let btn_cancelar;
    let is_loading = false;

    let load = function () {
        is_loading = true;
        get_productos()
            .then(function () {
                is_loading = false;
            }).catch(function (e) {
            console.log(e);
        });
    };

    function get_productos() {
        let xhr = $.get('producto/lista/1/0')
            .done(function (result) {
                lista_productos = result;
            })
            .fail(function (xhr, status, error) {
                show_error(error);
            });
        return xhr;
    }

    let init = function () {
        load();

        dg.datagrid({
            title: 'Ingresos',
            url: 'ingreso/get/1',
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    nuevo();
                }
            }],
            columns: [[{
                field: 'fecha',
                title: 'Fecha',
                sortable: true,
                width: 25
            }, {
                field: 'operacion',
                title: 'Operacion',
                sortable: true,
                width: 100
            }, {
                field: 'almacen',
                title: 'Almacen',
                sortable: true,
                width: 50
            }]],
            onDblClickRow: function () {
                //editar();
            },
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'ingreso/detalle/' + row.movimiento_id,
                    loadMsg: 'Procesando, por favor espere ...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [[{
                        field: 'producto',
                        title: 'Producto',
                        width: 300
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        width: 100,
                        align: 'center'
                    }, {
                        field: 'precio_unitario',
                        title: 'Unitario',
                        width: 100,
                        align: 'right'
                    }, {
                        field: 'precio_total',
                        title: 'Total',
                        width: 100,
                        align: 'right'
                    }]],
                    onResize: function () {
                        dg.datagrid('fixDetailRowHeight', index);
                    },
                    onLoadSuccess: function () {
                        setTimeout(function () {
                            dg.datagrid('fixDetailRowHeight', index);
                        }, 0);
                    }
                });
                dg.datagrid('fixDetailRowHeight', index);
            }
        });

        dg.datagrid('enableFilter', [{
            field: 'almacen',
            type: 'combobox',
            options: {
                url: 'almacen/lista/1/1',
                method: 'get',
                valueField: 'almacen_id',
                textField: 'descripcion',
                panelHeight: 200,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'almacen_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'almacen_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }, {
            field: 'operacion',
            type: 'combobox',
            options: {
                url: 'operacion/lista/1/0/1',
                method: 'get',
                valueField: 'operacion_id',
                textField: 'descripcion',
                panelHeight: 100,
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'operacion_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'operacion_id',
                            op: 'equal',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        dlg.dialog({
            width: 600,
            height: 540,
            buttons: [{
                id: 'ingreso_cancelar',
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog('close');
                }
            }, {
                id: 'ingreso_guardar',
                text: TEXT_SAVE,
                handler: function () {
                    guardar();
                }
            }],
            onBeforeOpen: function () {
                if (is_loading) {
                    show_info('Cargando datos...<br>Intente nuevamente en unos segundos.');
                    return false;
                }
            },
            onOpen: function () {
                dlg.dialog('center');
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_guardar = $('#ingreso_guardar');
                btn_cancelar = $('#ingreso_cancelar');
                btn_guardar.addClass('md-btn');
                btn_cancelar.addClass('md-btn-plain');
            }
        });

        db_fecha.datebox({
            width: '100%',
            label: 'Fecha',
            labelPosition: 'top',
            panelAlign: 'right',
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate',
            setValue: 'Today',
            readonly: true,
            required: true
        });

        cbo_operacion.combobox({
            width: '100%',
            label: 'Operación',
            labelPosition: 'top',
            url: 'operacion/lista/1/1/0',
            method: 'get',
            valueField: 'operacion_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        cbo_almacen.combobox({
            width: '100%',
            label: 'Almacen',
            labelPosition: 'top',
            url: 'almacen/lista/1/0',
            method: 'get',
            valueField: 'almacen_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true,
            required: true
        });

        cbo_proveedor.combobox({
            width: '100%',
            label: 'Proveedor',
            labelPosition: 'top',
            url: 'proveedor/lista/1/0',
            method: 'get',
            valueField: 'proveedor_id',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true
        });

        txt_glosa.textbox({
            width: '100%',
            height: 80,
            label: 'Glosa',
            labelPosition: 'top',
            multiline: true
        });

        btn_add.linkbutton({
            plain: true,
            onClick: function () {
                add_detalle();
            }
        });
    };

    function nuevo() {
        position = 1;
        tb_detalle.empty();
        add_detalle();
        fm.form('clear');
        route = 'ingreso/add/1';
        db_fecha.datebox('setValue', 'Today');
        dlg.dialog('open').dialog('setTitle', 'Nuevo');
        syncCheckbox_option()
    }

    function editar() {
        let row = dg.datagrid('getSelected');
        if (row) {
            dlg.dialog('open').dialog('setTitle', 'Editar');
            fm.form('load', row);
            route = 'ingreso/edit/' + row.movimiento_id;
            tb_detalle.empty();
            position = 1;
            load_detalle(row.movimiento_id);
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
                        show_info('Ya existe el Producto Ingresado ...');
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
                    $.post('ingreso/delete/' + row.movimiento_id)
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

    let add_detalle = function () {
        let template = list_template.html();
        template = template.replace(/%position%/g, position);
        tb_detalle.append(template);
        create(position, null);
        position++;
    };

    function load_detalle(id) {
        dlg.dialog('showMask', 'Cargando...');
        let template = list_template.html();
        $.post('ingreso/detalle/' + id)
            .done(function (result) {
                let detalle = result;
                for (let i = 0; i < detalle.length; i++) {
                    let obj = detalle[i];
                    let row = template.replace(/%position%/g, position);
                    tb_detalle.append(row);
                    create(position, obj);
                    position++;
                }
                dlg.dialog('hideMask');
            })
            .fail(function (xhr, status, error) {
                show_error(error);
                dlg.dialog('hideMask');
            });
    }

    function create(position, obj) {
        let cbo_producto = $('#ingreso_cbproducto_' + position);
        let txt_cantidad = $('#ingreso_tbcantidad_' + position);
        let txt_precio = $('#ingreso_tbprecio_' + position);
        let txt_subtotal = $('#ingreso_tbtotal_' + position);

        cbo_producto.combogrid({
            data: lista_productos,
            idField: 'producto_id',
            textField: 'descripcion',
            width: 250,
            panelWidth: 450,
            columns: [[
                {field: 'tipo_id', title: 'Tipo', formatter: typeFormatter, align: 'center'},
                {field: 'descripcion', title: 'Nombre'}
            ]],
            limitToList: true,
            reversed: true,
            required: true
        });

        txt_cantidad.numberbox({
            width: '70',
            min: 0,
            precision: 2,
            required: true,
            onChange: function () {
                calcular_subtotal(position);
            },
        });

        txt_precio.numberbox({
            width: '70',
            min: 0,
            precision: 2,
            onChange: function () {
                calcular_subtotal(position);
            }
        });

        txt_subtotal.numberbox({
            width: '70',
            min: 0,
            precision: 2,
            onChange: function () {
                calcular_unitario(position);
            }
        });

        if (obj != null) {
            cbo_producto.combogrid('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
            txt_precio.numberbox('setValue', obj.precio_unitario);
            txt_subtotal.numberbox('setValue', obj.precio_total);
        } else {
            cbo_producto.combogrid('clear').textbox('textbox').focus();
        }
    }

    function remove_row(position) {
        $('#ingreso_tr_' + position).remove();
        if (tb_detalle.children('tr').length == 0) {
            add_detalle();
        }
    }

    function calcular_unitario(position) {
        let txt_cantidad = $('#ingreso_tbcantidad_' + position);
        let txt_subtotal = $('#ingreso_tbtotal_' + position);
        let txt_unitario = $('#ingreso_tbprecio_' + position);
        let cantidad = Number(txt_cantidad.numberbox('getValue'));
        let subtotal = Number(txt_subtotal.numberbox('getValue'));
        let unitario = subtotal / cantidad;
        unitario = unitario.toFixed(2);
        txt_unitario.numberbox('setValue', unitario);
        calcular_total();
    }

    function calcular_subtotal(position) {
        let txt_cantidad = $('#ingreso_tbcantidad_' + position);
        let txt_unitario = $('#ingreso_tbprecio_' + position);
        let txt_subtotal = $('#ingreso_tbtotal_' + position);
        let cantidad = Number(txt_cantidad.numberbox('getValue'));
        let precio = Number(txt_unitario.numberbox('getValue'));
        let subtotal = cantidad * precio;
        subtotal = subtotal.toFixed(2);
        txt_subtotal.numberbox('setValue', subtotal);
        calcular_total();
    }

    function calcular_total() {
        let sum = 0;
        $('.ingreso_subtotal').each(function () {
            sum += Number($(this).numberbox('getValue'));
        });
        sum = sum.toFixed(2);
        $('#ingreso_total').html(sum);
    }

    function typeFormatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 1) {
            return '<span class="badge badge-pill badge-success">INSUMO</span>';
        } else if (value === 2) {
            return '<span class="badge badge-pill badge-info">PRODUCTO</span>';
        } else {
            return '';
        }
    }

    return {
        init: init,
        add_detalle: add_detalle,
        remove_row: remove_row
    };
})();
app.ingreso.init();
