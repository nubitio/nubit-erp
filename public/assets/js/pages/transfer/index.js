var app = app || {};
app.transfer = (function () {

    let route;
    let dg = $("#transfer_dg");
    let dlg = $("#transfer_dlg");
    let fm = $("#transfer_fm");
    let txt_fecha = $("#transfer_fecha");
    let txt_almacen_origen = $("#transfer_almacen_origen");
    let txt_almacen_destino = $("#transfer_almacen_destino");
    let txt_nota = $("#transfer_nota");
    let btn_save;
    let btn_cancel;

    let position = 1;
    let productList;
    let isLoading = false;
    let tbDetail = $('#transfer_tb_detail');
    let listTemplate = $('#transfer_template');

    let init = function () {
        isLoading = true;
        fetch('producto/lista/1/0')
            .then(response => response.json())
            .then(result => productList = result)
            .catch(error => show_error(error))
            .finally(() => isLoading = false);

        dg.datagrid({
            title: "Transferencias",
            url: "transfer/get",
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    insert();
                }
            }, "-", {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    update();
                }
            }, "-", {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    erase();
                }
            }],
            columns: [[
                {
                    field: "fecha",
                    title: "Fecha",
                },
                {
                    field: "origen",
                    title: "Almacen Origen",
                    width: 100
                },
                {
                    field: "destino",
                    title: "Almacen Destino",
                    width: 100
                },
                {
                    field: "nota",
                    title: "Nota",
                }
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            width: 550,
            height: 450,
            buttons: [{
                id: "transfer_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "transfer_save",
                text: TEXT_SAVE,
                handler: function () {
                    if (txt_almacen_origen.combobox('getValue') === txt_almacen_destino.combobox('getValue')) {
                        show_error('Almacen de origen y destino iguales');
                    } else {
                        save();
                    }
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#transfer_save");
                btn_cancel = $("#transfer_cancel");
                btn_save.addClass("md-btn");
                btn_cancel.addClass("md-btn-plain");
            }
        });

        txt_fecha.datebox({
            width: "100%",
            label: "Fecha",
            labelPosition: "top",
            formatter: dateformatter,
            parser: dateparser,
            required: true
        });

        txt_almacen_origen.combobox({
            width: "100%",
            label: "Almacen Origen",
            labelPosition: "top",
            url: 'almacen/lista/1/0',
            method: 'get',
            valueField: 'almacen_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true,
            required: true
        });

        txt_almacen_destino.combobox({
            width: "100%",
            label: "Almacen Destino",
            labelPosition: "top",
            url: 'almacen/lista/1/0',
            method: 'get',
            valueField: 'almacen_id',
            textField: 'descripcion',
            panelHeight: 'auto',
            limitToList: true,
            required: true
        });

        txt_nota.textbox({
            width: "100%",
            label: "Nota",
            labelPosition: "top",
            multiline: true,
            required: false
        });
    };

    function insert() {
        position = 1;
        tbDetail.empty();
        addDetail();
        fm.form("clear");
        route = "transfer/add";
        txt_fecha.datebox('setValue', 'Today');
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "transfer/edit/" + row.transfer_id;
            position = 1;
            tbDetail.empty();
            loadDetail(row.transfer_id);
        } else {
            show_info("Seleccione un registro.");
        }
    }

    function save() {
        fm.form("submit", {
            url: route,
            onSubmit: function () {
                let valid = fm.form("validate");
                if (valid) {
                    disable_buttons();
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        dlg.dialog("close");
                        dg.datagrid("reload");
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

    function erase() {
        let row = dg.datagrid("getSelected");
        if (row) {
            $.messager.confirm("Confirmar", TEXT_CONFIRM, function (r) {
                if (r) {
                    $.post("transfer/delete/" + row.transfer_id)
                        .done(function (result) {
                            if (result.success) {
                                dg.datagrid("reload");
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
            show_info("Seleccione un registro");
        }
    }

    function disable_buttons() {
        btn_cancel.linkbutton("disable");
        btn_save.linkbutton("disable");
        btn_save.linkbutton({text: "Guardando..."});
        dlg.dialog("showMask", "Procesando...");
    }

    function enable_buttons() {
        btn_cancel.linkbutton("enable");
        btn_save.linkbutton("enable");
        btn_save.linkbutton({text: TEXT_SAVE});
        dlg.dialog("hideMask");
    }

    let addDetail = function () {
        let template = listTemplate.html();
        template = template.replace(/%position%/g, position);
        tbDetail.append(template);
        create(position, null);
        position++;
    };

    function loadDetail(id) {
        dlg.dialog('showMask', 'Cargando...');
        let template = listTemplate.html();
        $.post('transfer/detalle/' + id)
            .done(function (result) {
                let detalle = result;
                for (let i = 0; i < detalle.length; i++) {
                    let obj = detalle[i];
                    let row = template.replace(/%position%/g, position);
                    tbDetail.append(row);
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
        let cbo_producto = $('#transfer_cbproducto_' + position);
        let txt_cantidad = $('#transfer_tbcantidad_' + position);

        cbo_producto.combogrid({
            data: productList,
            width: 340,
            idField: 'producto_id',
            textField: 'descripcion',
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
            width: '90',
            min: 0,
            precision: 2,
            onChange: function () {
                //checkStock(position);
            },
            required: true
        });

        if (obj != null) {
            cbo_producto.combogrid('select', obj.producto_id);
            txt_cantidad.numberbox('setValue', obj.cantidad);
        } else {
            cbo_producto.combogrid('clear').textbox('textbox').focus();
        }
    }

    function removeItem(position) {
        $('#transfer_tr_' + position).remove();
        if (tbDetail.children('tr').length === 0) {
            addDetail();
        }
    }

    function checkStock(position) {
        let cbo_producto = $('#transfer_cbproducto_' + position);
        let txt_cantidad = $('#transfer_tbcantidad_' + position);
        let producto = cbo_producto.combogrid('getValue');
        let cantidad = parseFloat(txt_cantidad.textbox('getValue'));
        let almacen = txt_almacen_origen.combobox('getValue');
        if (almacen && producto) {
            dlg.dialog('showMask', 'Consultando stock');
            $.post('salida/stock/' + producto + '/' + almacen)
                .done(function (result) {
                    if (result < cantidad) {
                        txt_cantidad.textbox('clear');
                        show_info('Producto sin stock y/o Stock insuficiente');
                    } else {
                        show_info('Stock: ' + result);
                    }
                    dlg.dialog('hideMask');
                })
                .fail(function (xhr, status, error) {
                    show_error(error);
                    dlg.dialog('hideMask');
                });
        } else {
            txt_cantidad.textbox('clear');
            show_info('Seleccione almacen y/o producto');
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

    return {
        init: init,
        addDetail: addDetail,
        removeItem: removeItem
    };
})();
app.transfer.init();
