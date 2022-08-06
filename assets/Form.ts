import {Constants} from "./Constants";
import {FormType} from "./FormType";
import {SharedUtils} from "./SharedUtils";
import {EventType} from "./EventType";
import {Field} from "./Field";

export default class Form {
    private container: HTMLElement;
    private form: JQuery;
    private fields: Field[];
    private formFields: any = {};
    private route: null;

    constructor(container, fields) {
        this.container = document.getElementById(container);
        this.form = null;
        this.fields = fields;
        this.formFields = {};
        this.route = null;
        this.init();
    }

    init() {
        this.setupView();
        this.buildFormFields();
        this.setupObservers();
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
            if (field.type !== FormType.HIDDEN) {
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

    setupObservers() {
        window.Observer.subscribe(EventType.ADD, (data) => {
            this.add(data.route);
        });

        window.Observer.subscribe(EventType.EDIT, (data) => {
            this.edit(data.route, data.row);
        });

        window.Observer.subscribe(EventType.DELETE, (data) => {
            this.delete(data.route, data.row);
        });

        window.Observer.subscribe(EventType.SAVE, () => {
            this.save();
        });

        window.Observer.subscribe(EventType.EXPORT, (data) => {
            window.open(data.route);
        });
    }

    buildFormFields() {
        this.fields.forEach(field => {
            switch (field.type) {
                case FormType.FILE:
                    this.buildFileInput(field);
                    break;
                case FormType.TEXT:
                    this.buildTextInput(field);
                    break;
                case FormType.TEXTAREA:
                    this.buildTextAreaInput(field);
                    break;
                case FormType.NUMBER:
                    this.buildNumberInput(field);
                    break;
                case FormType.SELECT:
                    this.buildSelectInput(field);
                    break;
                case FormType.SWITCH:
                    this.buildSwitchInput(field);
                    break;
                case FormType.CHECK:
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
            validType: field.validators,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildTextAreaInput(field) {
        let input = $('#' + field.name);
        input.textbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            height: field.height || 90,
            multiline: true,
            validType: field.validators,
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
            validType: field.validators,
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

    getIdField() {
        let idField = this.fields.find(field => field.isIdentity === true);
        return idField.name;
    }

    /**
     * @deprecated use getField instead
     */
    get field() {
        return this.formFields || {};
    }

    getField(name): JQuery {
        return this.formFields[name] || {};
    }

    add(route) {
        this.form.form('clear');
        this.route = route;

        this.fields.forEach(field => {
            if (field.type === FormType.SWITCH) {
                if (field.defaultValue) {
                    this.formFields[field.name].switchbutton('check');
                }
            }
        });
    }

    edit(route, row) {
        this.form.form('load', row);
        this.route = route + row[this.getIdField()];
    }

    save() {
        let ref = this;

        this.form.form('submit', {
            url: this.route,
            onSubmit: function () {
                let valid = ref.form.form('validate');
                if (valid) {
                    window.Observer.notify(EventType.LOADING, true);
                }
                return valid;
            },
            success: function (result) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        window.Observer.notify(EventType.SUCCESS, result.data || {});
                        SharedUtils.showMessage('Info', result.msg);
                    } else {
                        SharedUtils.showMessage('Error', result.msg);
                    }
                } catch (e) {
                    SharedUtils.showMessage('Error', e);
                } finally {
                    window.Observer.notify(EventType.LOADING, false);
                }
            }
        });
    }

    delete(route, row) {
        let ref = this;
        ($ as any).messager.confirm('Confirmar', Constants.TEXT_CONFIRM, function (r) {
            if (r) {
                $.post(route + row[ref.getIdField()])
                    .done(function (result) {
                        if (result.success) {
                            window.Observer.notify(EventType.SUCCESS);
                            SharedUtils.showMessage('Info', result.msg);
                        } else {
                            SharedUtils.showMessage('Error', result.msg);
                        }
                    })
                    .fail(function (xhr, status, error) {
                        SharedUtils.showMessage('Error', error);
                    });
            }
        });
    }
}