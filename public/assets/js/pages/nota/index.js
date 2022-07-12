var app = app || {};
app.nota = (function () {

    let route;
    let dg = $("#nota_dg");
    let dlg = $("#nota_dlg");
    let fm = $("#nota_fm");
    let txt_movimiento_id = $("#nota_movimiento_id");
    let txt_fecha = $("#nota_fecha");
    let txt_codigo_motivo = $("#nota_codigo_motivo");
    let txt_descripcion_motivo = $("#nota_descripcion_motivo");
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
            }, {
                text: 'Enviar Sunat',
                iconCls: 'fas fa-paper-plane fa-1x text-warning',
                handler: function () {
                    let row = dg.datagrid("getSelected");
                    if (row) {
                        $.messager.confirm('Confirmar', '¿Desea enviar el comprobante a Sunat?', function (r) {
                            if (r) {
                                sendNote(row);
                            }
                        });
                    } else {
                        show_info('Seleccione un registro');
                    }
                }
            }, '-', {
                text: 'Enviar Cliente',
                iconCls: 'fas fa-paper-plane fa-1x text-success',
                handler: function () {
                    sendEmailToClient();
                }
            }, '-', {
                text: 'Ver',
                iconCls: 'fas fa-eye fa-1x text-info',
                handler: function () {
                    viewDocument();
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

        txt_movimiento_id.combobox({
            width: "100%",
            label: "Comprobante",
            labelPosition: "top",
            url: 'invoice/noteList',
            method: 'get',
            valueField: 'movimiento_id',
            textField: 'comprobante',
            panelHeight: 200,
            limitToList: true,
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

        txt_codigo_motivo.combobox({
            width: "100%",
            label: "Motivo",
            labelPosition: "top",
            data: [{
                value: '01',
                text: 'Anulación de la operación'
            }, {
                value: '02',
                text: 'Anulación por error en el RUC'
            }, {
                value: '03',
                text: 'Corrección por error en la descripción'
            }, {
                value: '04',
                text: 'Descuento global'
            }, {
                value: '05',
                text: 'Descuento por ítem'
            }, {
                value: '06',
                text: 'Devolución total'
            }, {
                value: '07',
                text: 'Devolución por ítem'
            }, {
                value: '08',
                text: 'Bonificación'
            }, {
                value: '09',
                text: 'Disminución en el valor'
            }, {
                value: '10',
                text: 'Otros Conceptos'
            }],
            panelHeight: 'auto',
            limitToList: true,
            required: true
        });

        txt_descripcion_motivo.textbox({
            width: "100%",
            height: 80,
            label: "Descripcion Motivo",
            labelPosition: "top",
            multiline: true,
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
            if (isEditable(row)) {
                dlg.dialog("open").dialog("setTitle", "Editar");
                fm.form("load", row);
                route = "nota/edit/" + row.nota_id;
            } else {
                show_info("No se puede editar un registro enviado a la SUNAT.");
            }
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
                        show_info(result.message);
                    } else {
                        show_error(result.message);
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
            if (isEditable(row)) {
                $.messager.confirm("Confirmar", TEXT_CONFIRM, function (r) {
                    if (r) {
                        $.post("nota/delete/" + row.nota_id)
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
                show_error("No se puede eliminar una nota que ya se envio a la SUNAT.");
            }
        } else {
            show_info("Seleccione un registro");
        }
    }

    function isEditable(row) {
        let statusCode = -1;
        if (row.envio_sunat !== null) {
            statusCode = Number(row.envio_sunat);
        }
        return statusCode === -1;
    }

    function showDataGridLoading(msg) {
        dg.datagrid('options').loadMsg = msg;
        dg.datagrid('loading');
    }

    function hideDataGridLoading() {
        dg.datagrid('loaded');
        dg.datagrid('options').loadMsg = TEXT_LOADING;
    }

    function sendNote(note) {
        if (isEditable(note)) {
            fetch('nota/send/' + note.nota_id)
                .then(response => response.json())
                .then(result => show_info(result.msg))
                .catch(error => show_error(error))
                .finally(() => dg.datagrid('reload'));
        } else {
            show_info('No se puede enviar una nota que ya se envio a la SUNAT.');
        }
    }

    function sendEmailToClient() {
        let row = dg.datagrid('getSelected');
        if (row) {
            showDataGridLoading('Enviando mensaje');
            fetch('venta/enviarefact/' + row.nota_id)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        show_info(result.message)
                    } else {
                        show_error(result.message)
                    }
                })
                .catch(error => show_error(error))
                .finally(() => hideDataGridLoading());
        } else {
            show_info('Seleccione un registro');
        }
    }

    function viewDocument() {
        let row = dg.datagrid('getSelected');
        if (row) {
            window.open('invoice/' + row.nota_id);
        } else {
            show_info('Seleccione un registro');
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
            return '<span class="badge rounded-pill bg-secondary">PENDIENTE</span>';
        } else if (value === 0) {
            return '<span class="badge rounded-pill bg-success">ENVIADO</span>';
        } else if (value < 2000) {
            return '<span class="badge rounded-pill bg-warning">ERRONEO</span>';
        } else if (value >= 2000 && value < 4000) {
            return '<span class="badge rounded-pill bg-danger">RECHAZADO</span>';
        } else if (value >= 4000) {
            return '<span class="badge rounded-pill bg-info">OBSERVADO</span>';
        }
    }

    return {
        init: init
    };
})();
app.nota.init();
