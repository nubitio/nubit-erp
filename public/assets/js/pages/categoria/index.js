var app = app || {};
app.categoria = (function () {

    let route;
    let dg = $("#categoria_dg");
    let dlg = $("#categoria_dlg");
    let fm = $("#categoria_fm");
    let txt_name = $("#categoria_name");
    let txt_parent_id = $("#categoria_parent_id");
    let txt_active = $("#categoria_active");
    let btnOk;
    let btnCancel;

    let init = function () {
        dg.datagrid({
            title: "Categoria",
            url: "categoria/get",
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
                    field: "descripcion",
                    title: "Nombre",
                    width: 200,
                },
                {
                    field: "parent_id",
                    title: "Categoria padre",
                },
                {
                    field: "estado",
                    title: "Activo",
                    formatter: activeFormatter,
                },
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "categoria_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "categoria_ok",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btnOk = $("#categoria_ok");
                btnCancel = $("#categoria_cancel");
                btnOk.addClass("md-btn");
                btnCancel.addClass("md-btn-plain");
            }
        });

        txt_name.textbox({
            width: "100%",
            label: "Nombre",
            labelPosition: "top",
            required: true
        });

        txt_parent_id.combobox({
            width: "100%",
            label: "Categoria padre",
            labelPosition: "top",
            url: "categoria/lista/0",
            method: "get",
            valueField: "categoria_id",
            textField: "descripcion",
            panelHeight: 200,
            limitToList: true,
            required: false
        });

        txt_active.switchbutton({
            label: "Activo",
            labelPosition: "top",
            onText: "SI",
            offText: "NO",
            value: "1",
            required: true
        });

    };

    function insert() {
        fm.form("clear");
        route = "categoria/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "categoria/edit/" + row.categoria_id;
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
                    disableButtons(btnOk, btnCancel, dlg);
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
                    enableButtons(btnOk, btnCancel, dlg);
                } catch (e) {
                    show_error(e);
                    enableButtons(btnOk, btnCancel, dlg);
                }
            }
        });
    }

    function erase() {
        let row = dg.datagrid("getSelected");
        if (row) {
            $.messager.confirm("Confirmar", TEXT_CONFIRM, function (r) {
                if (r) {
                    $.post("categoria/delete/" + row.categoria_id)
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

    return {
        init: init
    };
})();
app.categoria.init();
