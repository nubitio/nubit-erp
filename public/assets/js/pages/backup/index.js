var app = app || {};
app.backup = (function () {

    let route;
    let dg = $("#backup_dg");
    let dlg = $("#backup_dlg");
    let fm = $("#backup_fm");
    let txt_observacion = $("#backup_observacion");
    let txt_path = $("#backup_path");
    let btnOk;
    let btnCancel;

    let init = function () {
        dg.datagrid({
            title: "Backup",
            url: "backup/get",
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
                text: TEXT_EXPORTAR,
                iconCls: ICON_PDF,
                handler: function () {
                    download();
                }
            }],
            columns: [[
                {
                    field: "correlativo",
                    title: "Correlativo",
                },
                {
                    field: "observacion",
                    title: "Observacion",
                    width: 100
                },
                {
                    field: "path",
                    title: "Path",
                    formatter: fileFormatter
                }, {
                    field: "created_at",
                    title: "Fecha"
                }, {
                    field: "username",
                    title: "Usuario"
                }
            ]],
            onDblClickRow: function () {
                update();
            }
        });

        dg.datagrid("enableFilter", [{}]);

        dlg.dialog({
            buttons: [{
                id: "backup_cancel",
                text: TEXT_CANCEL,
                handler: function () {
                    dlg.dialog("close");
                }
            }, {
                id: "backup_ok",
                text: TEXT_SAVE,
                handler: function () {
                    save();
                }
            }],
            onOpen: function () {
                dlg.dialog("center");
                dlg.parent().animateCss(DIALOG_OPEN_ANIMATION);
                btnOk = $("#backup_ok");
                btnCancel = $("#backup_cancel");
                btnOk.addClass("md-btn");
                btnCancel.addClass("md-btn-plain");
            }
        });

        txt_observacion.textbox({
            width: "100%",
            label: "Observacion",
            labelPosition: "top",
            required: false
        });

        txt_path.textbox({
            width: "100%",
            label: "Path",
            labelPosition: "top",
            required: false
        });

    };

    function insert() {
        fm.form("clear");
        route = "backup/add";
        dlg.dialog("open").dialog("setTitle", "Nuevo");
    }

    function update() {
        let row = dg.datagrid("getSelected");
        if (row) {
            dlg.dialog("open").dialog("setTitle", "Editar");
            fm.form("load", row);
            route = "backup/edit/" + row.id;
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
                    $.post("backup/delete/" + row.id)
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

    function download() {
        let row = dg.datagrid("getSelected");
        if (row) {
            window.open('backup/download/' + row.id)
        } else {
            show_info("Seleccione un registro.");
        }
    }

    function fileFormatter(value, row) {
        return '<a href="backup/download/' + row.id + '" target="_blank" class="text-reset"><i class="fa fa-file-download"></i> Descargar</a>'
    }

    return {
        init: init
    };
})();
app.backup.init();
