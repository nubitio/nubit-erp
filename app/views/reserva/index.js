var app = app || {};
app.reserva = (function () {

    let route;
    let dg = $("#reserva_dg");
    let dlg = $("#reserva_dlg");
    let fm = $("#reserva_fm");
    let txt_cliente_id = $("#reserva_cliente_id");
    let txt_fecha = $("#reserva_fecha");
    let txt_hora = $("#reserva_hora");
    let txt_comesales = $("#reserva_comesales");
    let txt_mesa_id = $("#reserva_mesa_id");
    let txt_observaciones = $("#reserva_observaciones");
    let btn_save;
    let btn_cancel;

    let init = function () {
        mainObserver.subscribe('customer:save', (data) => {
            txt_cliente_id.combobox('reload');
            txt_cliente_id.combobox('select', data.data)
            console.log(data);
        });

        dg.datagrid({
            title: "Reservas",
            url: "reserva/get",
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
                text: "Anular",
                iconCls: ICON_DELETE,
                handler: function () {
                    erase();
                }
            }],
            columns: [[
                {
                    field: "numero",
                    title: "Codigo",
                },
                {
                    field: "cliente",
                    title: "Cliente",
                    width: 200
                },
                {
                    field: "telefono",
                    title: "Telefono",
                },
                {
                    field: "fecha",
                    title: "Fecha",
                },
                {
                    field: "hora",
                    title: "Hora",
                },
                {
                    field: "comesales",
                    title: "Comesales",
                },
                {
                    field: "mesa",
                    title: "Mesa",
                },
                {
                    field: "observaciones",
                    title: "Observaciones",
                    width: 200
                },
                {
                    field: "cancelado",
                    title: "Anulado",
                    formatter: cancelledFormatter
                }
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "reserva_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "reserva_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#reserva_save");
                btn_cancel = $("#reserva_cancel");
                btn_save.addClass("md-btn");
                btn_cancel.addClass("md-btn-plain");
            }
        });

        txt_cliente_id.combobox({
            width: "100%",
            label: "Cliente",
            labelPosition: "top",
            url: 'cliente/lista/1/0',
            method: 'get',
            valueField: 'ruc',
            textField: 'razon_social',
            panelHeight: 200,
            limitToList: true,
            icons: [{
                iconCls: ICON_ADD,
                handler: function (e) {
                    app.customerForm.add()
                }
            }],
            required: true
        });

        txt_fecha.datebox({
            width: "100%",
            label: "Fecha",
            labelPosition: "top",
            formatter: dateformatter,
            parser: dateparser,
            required: true
        });

        txt_hora.timepicker({
            width: "100%",
            label: "Hora",
            labelPosition: "top",
            hour24: true,
            required: true
        });

        txt_comesales.numberspinner({
            width: "100%",
            label: "Comesales",
            labelPosition: "top",
            min: 1,
            required: true
        });

        txt_mesa_id.combobox({
            width: "100%",
            label: "Mesa",
            labelPosition: "top",
            url: 'mesa/lista/1/0',
            method: 'get',
            valueField: 'mesa_id',
            textField: 'descripcion',
            panelHeight: 200,
            limitToList: true,
            icons: [{
                iconCls: "fas fa-map",
                handler: function (e) {
                    show_info("Mapa de mesas no disponible")
                }
            }],
            required: true
        });

        txt_observaciones.textbox({
            width: "100%",
            label: "Observaciones",
            labelPosition: "top",
            height: 80,
            multiline: true,
            required: false
        });
    };

    function insert() {
        fm.form("clear");
        route = "reserva/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "reserva/edit/" + row.reserva_id;
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
                    $.post("reserva/delete/" + row.reserva_id)
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

    function cancelledFormatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === 0) {
            return '<span class="badge badge-pill badge-success">NO</span>';
        } else if (value === 1) {
            return '<span class="badge badge-pill badge-danger">SI</span>';
        } else {
            return '';
        }
    }

    return {
        init: init
    };
})();
app.reserva.init();
