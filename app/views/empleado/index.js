var app = app || {};
app.empleado = (function () {

    let route;
    let dg = $("#empleado_dg");
    let dlg = $("#empleado_dlg");
    let fm = $("#empleado_fm");
    let txt_dni = $("#empleado_dni");
    let txt_nombres = $("#empleado_nombres");
    let txt_apellidos = $("#empleado_apellidos");
    let txt_genero = $("#empleado_genero");
    let txt_fecha_nacimiento = $("#empleado_fecha_nacimiento");
    let txt_tipo_contrato = $("#empleado_tipo_contrato");
    let txt_fecha_ingreso = $("#empleado_fecha_ingreso");
    let txt_telefono = $("#empleado_telefono");
    let txt_email = $("#empleado_email");
    let txt_activo = $("#empleado_activo");
    let btn_save;
    let btn_cancel;
    let init = function () {
        dg.datagrid({
            title: "Empleado",
            url: "empleado/get",
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
                    field: "dni",
                    title: "Dni",
                },
                {
                    field: "nombres",
                    title: "Nombres",
                    width: 100
                },
                {
                    field: "apellidos",
                    title: "Apellidos",
                    width: 100
                },
                {
                    field: "genero",
                    title: "Genero",
                    formatter: genero_formatter
                },
                {
                    field: "fecha_nacimiento",
                    title: "Fecha Nacimiento",
                },
                {
                    field: "tipo_contrato",
                    title: "Tipo Contrato",
                    formatter: tipo_contrato_formatter
                },
                {
                    field: "fecha_ingreso",
                    title: "Fecha Ingreso",
                },
                {
                    field: "telefono",
                    title: "Telefono",
                },
                {
                    field: "email",
                    title: "Email",
                },
                {
                    field: "activo",
                    title: "Activo",
                    formatter: activo_formatter,
                    styler: activo_styler
                },
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "empleado_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "empleado_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#empleado_save");
                btn_cancel = $("#empleado_cancel");
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

        txt_nombres.textbox({
            width: "100%",
            label: "Nombres",
            labelPosition: "top",
            required: false
        });

        txt_apellidos.textbox({
            width: "100%",
            label: "Apellidos",
            labelPosition: "top",
            required: false
        });

        txt_genero.combobox({
            width: "100%",
            label: "Genero",
            labelPosition: "top",
            data: [{
                text: "Femenino",
                value: 1
            }, {
                text: "Masculino",
                value: 2
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });

        txt_fecha_nacimiento.datebox({
            width: "100%",
            label: "Fecha Nacimiento",
            labelPosition: "top",
            formatter: dateformatter,
            parser: dateparser,
            required: false
        });

        txt_tipo_contrato.combobox({
            width: "100%",
            label: "Tipo Contrato",
            labelPosition: "top",
            data: [{
                text: "Planilla",
                value: 1
            }, {
                text: "RH",
                value: 2
            }],
            panelHeight: 'auto',
            limitToList: true,
            editable: false,
            required: true
        });

        txt_fecha_ingreso.datebox({
            width: "100%",
            label: "Fecha Ingreso",
            labelPosition: "top",
            formatter: dateformatter,
            parser: dateparser,
            required: false
        });

        txt_telefono.textbox({
            width: "100%",
            label: "Telefono",
            labelPosition: "top",
            required: false
        });

        txt_email.textbox({
            width: "100%",
            label: "Email",
            labelPosition: "top",
            required: false
        });

        txt_activo.switchbutton({
            label: 'Activo',
            labelPosition: 'top',
            onText: "SI",
            offText: "NO",
            value: "1",
            required: false
        });
    };

    function insert() {
        fm.form("clear");
        route = "empleado/add";
        txt_activo.switchbutton('check');
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "empleado/edit/" + row.id;
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
                    $.post("empleado/delete/" + row.id)
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

    function genero_formatter(value) {
        if (value == 1) {
            return 'Femenino';
        } else if (value == 2) {
            return 'Masculino';
        } else {
            return '';
        }
    }

    function tipo_contrato_formatter(value) {
        if (value == 1) {
            return 'Planilla';
        } else if (value == 2) {
            return 'RE';
        } else {
            return '';
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
app.empleado.init();
