var app = app || {};
app.concentrados = (function () {

    let route;
    let dg = $("#concentrados_dg");
    let dlg = $("#concentrados_dlg");
    let fm = $("#concentrados_fm");
    let txt_fecha = $("#concentrados_fecha");
    let txt_tipo_doc = $("#concentrados_tipo_doc");
    let txt_serie_doc = $("#concentrados_serie_doc");
    let txt_cliente = $("#concentrados_cliente");
    let txt_detalle = $("#concentrados_detalle");
    let txt_tipo_pago = $("#concentrados_tipo_pago");
    let txt_monto = $("#concentrados_monto");
    let btn_save;
    let btn_cancel;
    let init = function () {
        dg.datagrid({
            title: "Concentrados",
            url: "concentrados/get",
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
            }, '-', {
                text: TEXT_EXPORTAR,
                iconCls: ICON_XLS,
                handler: function () {
                    exportar();
                }
            }],
            columns: [[
                {
                    field: "id",
                    title: "Id",
                },
                {
                    field: "fecha",
                    title: "Fecha",
                },
                {
                    field: "tipo_doc",
                    title: "Tipo Doc",
                },
                {
                    field: "serie_doc",
                    title: "Serie Doc",
                },
                {
                    field: "cliente",
                    title: "Cliente",
                    width: 100
                },
                {
                    field: "detalle",
                    title: "Detalle",
                },
                {
                    field: "tipo_pago",
                    title: "Tipo Pago",
                },
                {
                    field: "monto",
                    title: "Monto",
                },
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "concentrados_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "concentrados_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#concentrados_save");
                btn_cancel = $("#concentrados_cancel");
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
            required: false
        });

        txt_tipo_doc.combobox({
            width: "100%",
            label: "Tipo Doc",
            labelPosition: "top",
            data: [{
                value: 'RIC',
                text: 'Recibo de ingreso contado'
            }, {
                value: 'RIQ',
                text: 'Recibo de ingreso quincena'
            }, {
                value: 'RIS',
                text: 'Recibo de ingreso sacos'
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });

        txt_serie_doc.numberbox({
            width: "100%",
            label: "Serie Doc",
            labelPosition: "top",
            required: true
        });

        txt_cliente.combobox({
            width: "100%",
            label: "Cliente",
            labelPosition: "top",
            url: 'cliente/lista/1/0',
            method: 'get',
            valueField: 'ruc',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            required: true
        });

        txt_detalle.textbox({
            width: "100%",
            label: "Detalle",
            labelPosition: "top",
            required: false
        });

        txt_tipo_pago.combobox({
            width: "100%",
            label: "Tipo Pago",
            labelPosition: "top",
            data: [{
                value: 'C',
                text: 'Contado'
            }, {
                value: 'Q',
                text: 'Quincena'
            }, {
                value: 'S',
                text: 'Sacos'
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });

        txt_monto.numberbox({
            width: "100%",
            label: "Monto",
            labelPosition: "top",
            required: false
        });

    };

    function exportar() {
        window.open('concentrados/exportar');
    }

    function insert() {
        fm.form("clear");
        route = "concentrados/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "concentrados/edit/" + row.id;
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
                    $.post("concentrados/delete/" + row.id)
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

    return {
        init: init
    };
})();
app.concentrados.init();
