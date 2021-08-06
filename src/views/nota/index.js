var app = app || {};
var accounting = app.accounting || {}
app.nota = (function () {

    let route;
    let dg = $("#nota_dg");
    let dlg = $("#nota_dlg");
    let fm = $("#nota_fm");
    let txt_movimiento_id = $("#nota_movimiento_id");
    let txt_fecha = $("#nota_fecha");
    let txt_tipo_id = $("#nota_tipo_id");
    let txt_codigo_motivo = $("#nota_codigo_motivo");
    let txt_descripcion_motivo = $("#nota_descripcion_motivo");
    let txt_serie = $("#nota_serie");
    let txt_correlativo = $("#nota_correlativo");
    let txt_digest = $("#nota_digest");
    let txt_envio_sunat = $("#nota_envio_sunat");
    let txt_mensaje_sunat = $("#nota_mensaje_sunat");
    let btn_save;
    let btn_cancel;
    let init = function () {
        dg.datagrid({
            title: "Nota",
            url: "nota/get",
            remoteFilter: true,
            toolbar: [{
                text: TEXT_ADD,
                iconCls: ICON_ADD,
                handler: function () {
                    show_info('Función en desarrollo');
                    //insert();
                }
            }, "-", {
                text: TEXT_EDIT,
                iconCls: ICON_EDIT,
                handler: function () {
                    show_info('Función en desarrollo');
                    //update();
                }
            }, "-", {
                text: TEXT_DELETE,
                iconCls: ICON_DELETE,
                handler: function () {
                    show_info('Función en desarrollo');
                    //erase();
                }
            }, '-', {
                text: 'Resumen diario',
                iconCls: 'fas fa-file-invoice fa-1x text-primary',
                handler: function () {
                    accounting.summary.open(2);
                }
            }],
            columns: [[
                {
                    field: "fecha",
                    title: "Fecha",
                },
                {
                    field: "tipo_id",
                    title: "Tipo Id",
                },
                {
                    field: "codigo_motivo",
                    title: "Codigo Motivo",
                },
                {
                    field: "descripcion_motivo",
                    title: "Descripcion Motivo",
                    width: 200
                },
                {
                    field: "serie",
                    title: "Serie",
                },
                {
                    field: "correlativo",
                    title: "Correlativo",
                },
                {
                    field: "doc_afectado",
                    title: "Doc. Afectado"
                },
                {
                    field: "envio_sunat",
                    title: "Envio Sunat",
                    formatter: sunat_formatter
                },
                {
                    field: "mensaje_sunat",
                    title: "Mensaje Sunat",
                    width: 200
                },
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "nota_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "nota_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#nota_save");
                btn_cancel = $("#nota_cancel");
                btn_save.addClass("md-btn");
                btn_cancel.addClass("md-btn-plain");
            }
        });

        txt_movimiento_id.textbox({
            width: "100%",
            label: "Movimiento Id",
            labelPosition: "top",
            required: false
        });

        txt_fecha.datebox({
            width: "100%",
            label: "Fecha",
            labelPosition: "top",
            formatter: dateformatter,
            parser: dateparser,
            required: false
        });

        txt_tipo_id.textbox({
            width: "100%",
            label: "Tipo Id",
            labelPosition: "top",
            required: false
        });

        txt_codigo_motivo.textbox({
            width: "100%",
            label: "Codigo Motivo",
            labelPosition: "top",
            required: false
        });

        txt_descripcion_motivo.textbox({
            width: "100%",
            label: "Descripcion Motivo",
            labelPosition: "top",
            required: false
        });

        txt_serie.textbox({
            width: "100%",
            label: "Serie",
            labelPosition: "top",
            required: false
        });

        txt_correlativo.numberbox({
            width: "100%",
            label: "Correlativo",
            labelPosition: "top",
            required: false
        });

        txt_digest.textbox({
            width: "100%",
            label: "Digest",
            labelPosition: "top",
            required: false
        });

        txt_envio_sunat.numberbox({
            width: "100%",
            label: "Envio Sunat",
            labelPosition: "top",
            required: false
        });

        txt_mensaje_sunat.textbox({
            width: "100%",
            label: "Mensaje Sunat",
            labelPosition: "top",
            required: false
        });

    };

    function insert() {
        fm.form("clear");
        route = "nota/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "nota/edit/" + row.id;
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
                    $.post("nota/delete/" + row.id)
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

    function sunat_formatter(value, row) {
        if (value === null) {
            value = -1
        } else {
            value = Number(value);
        }
        if (value === -1) {
            return '<span class="badge badge-pill badge-secondary">PENDIENTE</span>';
        } else if (value === 0) {
            return '<span class="badge badge-pill badge-success">ENVIADO</span>';
        } else if (value < 2000) {
            return '<span class="badge badge-pill badge-warning">ERRONEO</span>';
        } else if (value >= 2000 && value < 4000) {
            return '<span class="badge badge-pill badge-danger">RECHAZADO</span>';
        } else if (value >= 4000) {
            return '<span class="badge badge-pill badge-info">OBSERVADO</span>';
        }
    }

    return {
        init: init
    };
})();
app.nota.init();
