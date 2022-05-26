class Form {
    constructor(form, dataGrid, dialog) {
        this.form = $('#' + form);
        this.dataGrid = $('#' + dataGrid);
        this.dialog = dialog;
        this.route = null;
        this.init();
    }

    init() {
    }

    add(route) {
        this.form.form('clear');
        this.route = route;
        this.dialog.show('Nuevo');
    }

    update(route, id) {
        let row = this.dataGrid.datagrid('getSelected');
        if (row) {
            this.dialog.show('Editar');
            this.form.form('load', row);
            this.route = route + row[id];
        } else {
            show_info('Seleccione un registro');
        }
    }

    save() {
        let ref = this;

        this.form.form('submit', {
            url: this.route,
            onSubmit: function () {
                let valid = ref.form.form('validate');
                if (valid) {
                    ref.dialog.setLoading(true);
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        ref.dialog.hide();
                        ref.dataGrid.datagrid('reload');
                        show_info(result.msg);
                    } else {
                        show_error(result.msg);
                    }
                    ref.dialog.setLoading(false);
                } catch (e) {
                    show_error(e);
                    ref.dialog.setLoading(false);
                }
            }
        });
    }

    delete(route, id) {
        let ref = this;
        let row = this.dataGrid.datagrid('getSelected');
        if (row) {
            $.messager.confirm('Confirmar', TEXT_CONFIRM, function (r) {
                if (r) {
                    $.post(route + row[id])
                        .done(function (result) {
                            if (result.success) {
                                ref.dataGrid.datagrid('reload');
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
            show_info('Seleccione un registro');
        }
    }
}