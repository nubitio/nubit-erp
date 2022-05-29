class Form {
    constructor(container, dataGrid, dialog, fields) {
        this.container = document.getElementById(container);
        this.form = null;
        this.dataGrid = $('#' + dataGrid);
        this.dialog = dialog;
        this.fields = fields;
        this.formFields = {};
        this.route = null;
        this.init();
    }

    init() {
        this.setupView();
        this.buildFormFields();
    }

    setupView() {
        let form = document.createElement('form');
        form.classList.add('fm');
        form.setAttribute('id', 'form');
        form.setAttribute('method', 'post');
        form.setAttribute('enctype', 'multipart/form-data');
        this.container.appendChild(form);
        this.form = $('#' + form.id);

        this.fields.forEach(field => {
            if (field.type !== FORM_TYPE.HIDDEN) {
                let fieldContainer = document.createElement('div');
                fieldContainer.setAttribute('class', 'mb-2');

                let input = document.createElement('input');
                input.setAttribute('id', field.name);
                input.setAttribute('name', field.name);

                fieldContainer.appendChild(input);
                form.appendChild(fieldContainer);
            }
        });
    }

    buildFormFields() {
        this.fields.forEach(field => {
            switch (field.type) {
                case FORM_TYPE.FILE:
                    this.buildFileInput(field);
                    break;
                case FORM_TYPE.TEXT:
                    this.buildTextInput(field);
                    break;
                case FORM_TYPE.NUMBER:
                    this.buildNumberInput(field);
                    break;
                case FORM_TYPE.SELECT:
                    this.buildSelectInput(field);
                    break;
                case FORM_TYPE.SWITCH:
                    this.buildSwitchInput(field);
                    break;
                case FORM_TYPE.CHECK:
                    break;
                default:
                    break;
            }
        });
    }

    buildFileInput(field) {
        let input = $('#' + field.name);
        input.filebox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            buttonText: 'Seleccionar',
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildTextInput(field) {
        let input = $('#' + field.name);
        input.textbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildNumberInput(field) {
        let input = $('#' + field.name);
        input.numberbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            min: 0,
            precision: 2,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSelectInput(field) {
        let input = $('#' + field.name);
        input.combobox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            data: field.data,
            panelHeight: 200,
            limitToList: true,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSwitchInput(field) {
        let input = $('#' + field.name);
        input.switchbutton({
            label: field.label,
            labelPosition: 'top',
            onText: "SI",
            offText: "NO",
            value: "1",
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    get field() {
        return this.formFields;
    }

    add(route) {
        this.form.form('clear');
        this.route = route;
        this.dialog.show('Nuevo');
    }

    edit(route, id) {
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