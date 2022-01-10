var app = app || {};
app.options = (function () {

    let route;
    let dg = $("#options_dg");
    let dlg = $("#options_dlg");
    let fm = $("#options_fm");
    let txt_name = $("#options_name");
    let txt_value = $("#options_value");
    let btn_save;
    let btn_cancel;
    let init = function () {
        dg.datagrid({
            title: "Options",
            url: "option/get",
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
                    field: "id",
                    title: "Id",
                },
                {
                    field: "name",
                    title: "Name",
                },
                {
                    field: "value",
                    title: "Value",
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
                id: "options_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "options_save",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btn_save = $("#options_save");
                btn_cancel = $("#options_cancel");
                btn_save.addClass("md-btn");
                btn_cancel.addClass("md-btn-plain");
            }
        });

        txt_name.textbox({
            width: "100%",
            label: "Name",
            labelPosition: "top",
            required: false
        });

        txt_value.textbox({
            width: "100%",
            label: "Value",
            labelPosition: "top",
            required: false
        });

    };

    function insert() {
        fm.form("clear");
        route = "option/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "option/edit/" + row.id;
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
                    location.reload();
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
                    $.post("option/delete/" + row.id)
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
app.options.init();
