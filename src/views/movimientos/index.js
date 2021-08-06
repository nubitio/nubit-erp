var app = app || {};
app.movimientos = (function () {
    let dg = $('#movimientos_dg');
    let toolbar = $('#movimientos_toolbar');
    let cbo_almacen = $('#movimientos_almacen');
    let cbo_categoria = $('#movimientos_categoria');
    let btn_kardex = $('#movimientos_kardex');
    let btn_reporte = $('#movimientos_reporte');
    let db_fecha_inicio = $('#movimientos_fi');
    let db_fecha_fin = $('#movimientos_ff');
    let btn_filtrar = $('#movimientos_filtrar');
    let fi, ff;

    let init = function () {
        dg.datagrid({
            title: 'Movimientos',
            url: 'movimientos/getall',
            toolbar: toolbar,
            remoteFilter: false,
            columns: [[{
                field: 'tipo_id',
                title: 'Tipo',
                sortable: true,
                formatter: typeFormatter,
                align: 'center',
                width: 50
            }, {
                field: 'producto',
                title: 'Producto',
                sortable: true,
                width: 350
            }, {
                field: 'saldo',
                title: 'Saldo',
                sortable: true,
                align: 'right'
            }, {
                field: 'ingresos',
                title: 'Ingresos',
                sortable: true,
                align: 'right'
            }, {
                field: 'salidas',
                title: 'Salidas',
                sortable: true,
                align: 'right'
            }, {
                field: 'stock',
                title: 'Stock',
                sortable: true,
                align: 'right'
            }]],
            view: detailview,
            detailFormatter: function (index, row) {
                return '<div style="padding:2px"><table class="ddv"></table></div>';
            },
            onExpandRow: function (index, row) {
                let ddv = $(this).datagrid('getRowDetail', index).find('table.ddv');
                ddv.datagrid({
                    url: 'movimientos/mdetalle/' + row.producto_id,
                    queryParams: {
                        fi: db_fecha_inicio.datebox('getValue'),
                        ff: db_fecha_fin.datebox('getValue'),
                        almacen: cbo_almacen.combobox('getValue')
                    },
                    loadMsg: 'Procesando, por favor espere...',
                    height: 'auto',
                    fit: false,
                    border: true,
                    pagination: false,
                    multiSort: false,
                    remoteFilter: false,
                    columns: [[{
                        field: 'fecha',
                        title: 'Fecha',
                        align: 'center',
                        width: 50
                    }, {
                        field: 'tipo_id',
                        title: 'Tipo',
                        formatter: kindFormatter,
                        align: 'center',
                        width: 50
                    }, {
                        field: 'almacen',
                        title: 'Almacen',
                        width: 100
                    }, {
                        field: 'operacion',
                        title: 'Operacion',
                        width: 100
                    }, {
                        field: 'cantidad',
                        title: 'Cantidad',
                        align: 'center',
                        width: 50
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
            field: 'tipo_id',
            type: 'combobox',
            options: {
                panelHeight: 'auto',
                data: [{
                    value: '',
                    text: 'TODO'
                }, {
                    value: 'INSUMO',
                    text: 'INSUMO'
                }, {
                    value: 'PRODUCTO',
                    text: 'PRODUCTO'
                }],
                onChange: function (value) {
                    if (value === '') {
                        dg.datagrid('removeFilterRule', 'tipo_id');
                    } else {
                        dg.datagrid('addFilterRule', {
                            field: 'tipo_id',
                            op: 'contains',
                            value: value
                        });
                    }
                    dg.datagrid('doFilter');
                }
            }
        }]);

        cbo_almacen.combobox({
            width: 170,
            url: 'almacen/lista/1/1',
            method: 'get',
            valueField: 'almacen_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true
        });

        cbo_categoria.combobox({
            width: 170,
            url: 'categoria/lista/1/1',
            method: 'get',
            valueField: 'categoria_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true
        });

        db_fecha_inicio.datebox({
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate'
        });
        db_fecha_inicio.datebox('setValue', dateformatter(firstDay));

        db_fecha_fin.datebox({
            formatter: dateformatter,
            parser: dateparser,
            validType: 'validDate'
        });
        db_fecha_fin.datebox('setValue', 'Today');

        btn_kardex.linkbutton({
            iconCls: ICON_XLS,
            onClick: function () {
                kardex();
            }
        });

        btn_reporte.linkbutton({
            iconCls: ICON_XLS,
            onClick: function () {
                reporte();
            }
        });

        btn_filtrar.linkbutton({
            iconCls: 'fas fa-search fa-1x text-info',
            onClick: function () {
                filtrar();
            }
        });
    };

    //funciones

    function kardex() {
        let row = dg.datagrid('getSelected');
        fi = db_fecha_inicio.datebox('getValue');
        ff = db_fecha_fin.datebox('getValue');
        let almacen = cbo_almacen.combobox('getValue');
        if (row) {
            if (fi && ff && almacen !== '') {
                window.open('movimientos/kardex/' + almacen + '/' + row.producto_id + '/' + fi + '/' + ff);
            } else {
                show_info('Selecione un almacen, fecha inicial y final');
            }
        } else {
            show_info('Seleccione un registro');
        }
    }

    function reporte() {
        fi = db_fecha_inicio.datebox('getValue');
        ff = db_fecha_fin.datebox('getValue');
        let almacen = cbo_almacen.combobox('getValue');
        let categoria = cbo_categoria.combobox('getValue');
        if (fi && ff) {
            if (almacen === '') {
                almacen = 0;
            }
            if (categoria === '') {
                categoria = 0;
            }
            window.open('movimientos/reporte/' + almacen + '/' + categoria + '/' + fi + '/' + ff);
        } else {
            show_info('Rellene las Fechas');
        }
    }

    function filtrar() {
        fi = db_fecha_inicio.datebox('getValue');
        ff = db_fecha_fin.datebox('getValue');
        let almacen = cbo_almacen.combobox('getValue');
        let categoria = cbo_categoria.combobox('getValue');

        if (fi && ff) {
            if (almacen === '') {
                almacen = 0;
            }
            if (categoria === '') {
                categoria = 0;
            }
            dg.datagrid('load', {
                fi: fi,
                ff: ff,
                almacen: almacen,
                categoria: categoria
            });
        }
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

    function kindFormatter(value, row) {
        if (value === null) {
            value = -1
        }
        if (value === 'INGRESO') {
            return '<span class="badge badge-pill badge-success">INGRESO</span>';
        } else if (value === 'SALIDA') {
            return '<span class="badge badge-pill badge-danger">SALIDA</span>';
        } else {
            return '';
        }
    }

    return {
        init: init
    };
})();
app.movimientos.init();
