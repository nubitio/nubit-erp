var app = app || {};
app.asistencia = (function () {

    let route;
    let dg = $("#asistencia_dg");
    let dlg = $("#asistencia_dlg");
    let fm = $("#asistencia_fm");
    let txt_dni = $("#asistencia_dni");
    let txt_fecha = $("#asistencia_fecha");
    let txt_hora = $("#asistencia_hora");
    let btn_save;
    let btn_cancel;

    let report_dlg = $('#asiste_dlg');
    let fm_report = $('#asiste_fm');
    let txt_report_empleado = $('#asiste_empleado');
    let txt_report_desde = $('#asiste_fecha_desde');
    let txt_report_hasta = $('#asiste_fecha_hasta');

    let init = function () {
        dg.datagrid({
            title: "Asistencia",
            url: "asistencia/get",
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
            }, "-", {
                text: TEXT_REPORTE,
                iconCls: ICON_XLS,
                handler: function () {
                    report();
                }
            }],
            columns: [[
                {
                    field: "dni",
                    title: "Dni",
                },
                {
                    field: "empleado",
                    title: "Empleado",
                    width: 200
                },
                {
                    field: "fecha",
                    title: "Fecha",
                },
                {
                    field: "hora",
                    title: "Hora",
                },
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "asistencia_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "asistencia_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#asistencia_save");
                btn_cancel = $("#asistencia_cancel");
                btn_save.addClass("md-btn");
                btn_cancel.addClass("md-btn-plain");
            }
        });

        txt_dni.textbox({
            width: "100%",
            label: "Dni",
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

        txt_hora.timespinner({
            width: "100%",
            label: "Hora",
            labelPosition: "top",
            showSeconds: true,
            required: false
        });

        report_dlg.dialog({
            title: 'Reporte de asistencia',
            width: DIALOG_WIDTH,
            height: DIALOG_HEIGHT,
            inline: INLINE,
            shadow: SHADOW,
            closable: false,
            closed: CLOSED,
            toolbar: [{
                text: 'Reporte diario',
                iconCls: ICON_PDF,
                handler: function () {
                    reporte('diaria');
                }
            }, {
                text: 'Reporte mensual',
                iconCls: ICON_PDF,
                handler: function () {
                    reporte('mensual');
                }
            }/*, {
                text: 'Reporte resumen',
                iconCls: ICON_PDF,
                handler: function () {
                    reporte('resumen');
                }
            }*/],
            buttons: [{
                text: 'Cerrar',
                iconCls: ICON_CANCEL,
                handler: function () {
                    report_dlg.dialog('close');
                }
            }],
            onOpen: function () {
                report_dlg.dialog('center');
            }
        });

        txt_report_empleado.combobox({
            width: '100%',
            label: 'Empleado',
            labelPosition: 'top',
            method: 'get',
            url: 'empleado/list/1',
            valueField: 'dni',
            textField: 'empleado',
            panelHeight: 200,
            limitToList: true
        });

        txt_report_desde.datebox({
            width: '100%',
            label: 'Desde',
            labelPosition: 'top',
            formatter: dateformatter,
            parser: dateparser,
            panelAlign: 'right'
        });

        txt_report_hasta.datebox({
            width: '100%',
            label: 'Hasta',
            labelPosition: 'top',
            formatter: dateformatter,
            parser: dateparser,
            panelAlign: 'right'
        });
    };

    function insert() {
        fm.form("clear");
        route = "asistencia/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "asistencia/edit/" + row.id;
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
                    $.post("asistencia/delete/" + row.id)
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

    function report() {
        fm_report.form('clear');
        report_dlg.dialog("open");
    }

    function reporte(tipo) {
        let empleado = txt_report_empleado.combobox('getValue');
        let fecha_desde = txt_report_desde.datebox('getValue');
        let fecha_hasta = txt_report_hasta.datebox('getValue');
        switch (tipo) {
            case 'diaria':
                if (fecha_desde) {
                    report_dlg.dialog('close');
                    window.open('reporte/asistencia/' + tipo + '/' + fecha_desde);
                } else {
                    show_error('La fecha desde es obligatoria para este reporte')
                }
                break;
            case 'mensual':
                if (empleado && fecha_desde && fecha_hasta) {
                    report_dlg.dialog('close');
                    window.open('reporte/asistencia/' + tipo + '/' + empleado + '/' + fecha_desde + '/' + fecha_hasta);
                } else {
                    show_error("Todos los campos son obligatorios");
                }
                break;
        }
    }

    return {
        init: init
    };
})();
app.asistencia.init();
