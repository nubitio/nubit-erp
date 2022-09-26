import {FormType} from "./FormType";
import {SharedUtils} from "./utils/SharedUtils";
import {EventType} from "./EventType";
import {Field} from "./Field";
import {DateUtils} from "./utils/DateUtils";
import {bus} from "./EventBus";

export default class Form {
    private container: HTMLElement;
    private readonly fields: Field[];
    private readonly detailFields: Field[];
    private options: any = {};
    private form: JQuery;
    private readonly formFields: any = {};
    private route: null;
    private rowIndex = 0;

    constructor(container, fields: Field[], detailFields: Field[] = [], options = {}) {
        this.container = document.getElementById(container);
        this.fields = fields;
        this.detailFields = detailFields;
        this.options = options || {};
        this.form = null;
        this.formFields = {};
        this.route = null;
        this.init();
    }

    init() {
        this.setupView();
        this.buildFormFields(this.fields);

        let ref = this;
        this.form.form({
            iframe: false,
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

        this.setupObservers();
    }

    setupView() {
        let form = document.createElement('form');
        form.classList.add('fm');
        let randomId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        form.setAttribute('id', 'form' + randomId);
        form.setAttribute('method', 'post');
        form.setAttribute('enctype', 'multipart/form-data');
        this.container.appendChild(form);
        this.form = $('#' + form.id);

        if (this.detailFields.length > 0) {
            let row = document.createElement('div');
            row.classList.add('row');
            form.appendChild(row);

            let leftCol = document.createElement('div');
            leftCol.classList.add('col-md-4');
            row.appendChild(leftCol);

            let rightCol = document.createElement('div');
            rightCol.classList.add('col-md-8');
            row.appendChild(rightCol);

            this.buildFields(leftCol, this.fields);

            let headerContainer = document.createElement('div');
            headerContainer.classList.add('mb-2');

            let headerTitle = document.createElement('h4');
            headerTitle.innerHTML = 'Items';

            let leftItems = document.createElement('div');
            leftItems.classList.add('float-start');

            let rightItems = document.createElement('div');
            rightItems.classList.add('float-end');

            let clearFix = document.createElement('div');
            clearFix.classList.add('clearfix');

            let addRowBtn = document.createElement('button');
            addRowBtn.setAttribute('type', 'button');
            addRowBtn.setAttribute('class', 'btn btn-primary');
            addRowBtn.innerHTML = 'Agregar';
            addRowBtn.addEventListener('click', () => {
                this.addRow();
            });

            leftItems.appendChild(headerTitle);
            rightItems.appendChild(addRowBtn);

            headerContainer.appendChild(leftItems);
            headerContainer.appendChild(rightItems);
            headerContainer.appendChild(clearFix);
            rightCol.appendChild(headerContainer);

            let detailTable = document.createElement('table');
            detailTable.setAttribute('id', 'detailTable');
            detailTable.setAttribute('class', 'table table-bordered');
            rightCol.appendChild(detailTable);

            let tableHead = document.createElement('thead');

            let tableRow = document.createElement('tr');

            this.detailFields.forEach(field => {
                let tableHeader = document.createElement('th');
                tableHeader.innerHTML = field.label;
                tableRow.appendChild(tableHeader);
            });

            let tableHeader = document.createElement('th');
            tableRow.appendChild(tableHeader);

            tableHead.appendChild(tableRow);
            detailTable.appendChild(tableHead);

            let tableBody = document.createElement('tbody');
            tableBody.setAttribute('id', 'detailTableBody');
            detailTable.appendChild(tableBody);
        } else {
            this.buildFields(form, this.fields);
        }
    }

    private addRow() {
        this.rowIndex++;
        let detailTableBody = document.getElementById('detailTableBody');
        let tableRow = document.createElement('tr');
        tableRow.setAttribute('id', 'detailRow' + this.rowIndex);
        detailTableBody.appendChild(tableRow);

        let rowFields = this.detailFields.map(field => {
            return Object.assign({}, field);
        }) as Field[];

        this.buildFields(tableRow, rowFields, 'td', this.rowIndex);

        rowFields.forEach(field => {
            field.name = field.name + '-' + this.rowIndex;
            field.label = '';
        });

        this.buildFormFields(rowFields);
    }

    private removeRow(rowIndex) {
        let row = document.getElementById('detailRow' + rowIndex);
        row.parentNode.removeChild(row);
    }

    private buildFields(container, fields, containerType = 'div', rowIndex = null) {
        fields.forEach(field => {
            if (field.type === FormType.HIDDEN) {
                return;
            }
            let fieldContainer = document.createElement(containerType);
            if (containerType === 'div') {
                fieldContainer.setAttribute('class', 'mb-2');
            }

            let input = document.createElement('input');
            input.setAttribute('id', field.name + (rowIndex ? '-' + rowIndex : ''));
            if (containerType === 'td') {
                let index = rowIndex - 1;
                input.setAttribute('name', `detail[${index}][${field.name}]`);
            } else {
                input.setAttribute('name', field.name);
            }
            fieldContainer.appendChild(input);
            container.appendChild(fieldContainer);
        });

        if (containerType === 'td') {
            let fieldContainer = document.createElement(containerType);
            let removeBtn = document.createElement('button');
            removeBtn.setAttribute('type', 'button');
            removeBtn.setAttribute('class', 'btn btn-danger');
            removeBtn.innerHTML = '<i class="fa fa-trash"></i>';
            removeBtn.addEventListener('click', () => {
                this.removeRow(rowIndex);
            });
            fieldContainer.appendChild(removeBtn);
            container.appendChild(fieldContainer);
        }
    }

    setupObservers() {
        window.Observer.subscribe(EventType.ADD, (data) => {
            this.add(data.route);
        });

        window.Observer.subscribe(EventType.EDIT, (data) => {
            this.edit(data.route, data.row);
        });

        window.Observer.subscribe(EventType.SAVE, () => {
            this.submit();
        });

        bus.on(
            event => event.name === EventType.ADD,
            (event) => {
                console.log(event);
                this.add();
            }
        )

        bus.on(
            event => event.name === EventType.EDIT,
            (event) => {
                this.edit(null, event.data.row);
            }
        )

        bus.on(
            event => event.name === EventType.SAVE,
            (event) => {
                this.submit();
            }
        )
    }

    buildFormFields(fields: Field[]) {
        fields.forEach(field => {
            switch (field.type) {
                case FormType.FILE:
                    this.buildFileInput(field);
                    break;
                case FormType.TEXT:
                    this.buildTextInput(field);
                    break;
                case FormType.PASSWORD:
                    this.buildPasswordInput(field);
                    break;
                case FormType.TEXTAREA:
                    this.buildTextAreaInput(field);
                    break;
                case FormType.DATE:
                    this.buildDateInput(field);
                    break;
                case FormType.NUMBER:
                    this.buildNumberInput(field);
                    break;
                case FormType.CURRENCY:
                    this.buildCurrencyInput(field);
                    break;
                case FormType.SELECT:
                    this.buildSelectInput(field);
                    break;
                case FormType.ENUM:
                    this.buildEnumInput(field);
                    break;
                case FormType.ENTITY:
                    this.buildEntityInput(field);
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

    buildPasswordInput(field) {
        let input = $('#' + field.name);
        input.passwordbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
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

    buildDateInput(field) {
        let input = $('#' + field.name);
        input.datebox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            formatter: DateUtils.dateFormatter,
            parser: DateUtils.dateParser,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildNumberInput(field: Field) {
        let input = $('#' + field.name);
        input.numberbox({
            width: '100%',
            minWidth: 60,
            label: field.label,
            labelPosition: 'top',
            min: 0,
            validType: field.validators,
            onChange: (newValue, oldValue) => {
                if (field.onChange) {
                    field.onChange(newValue, oldValue, field);
                }
            },
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildCurrencyInput(field: Field) {
        let input = $('#' + field.name);
        input.numberbox({
            width: '100%',
            minWidth: 80,
            label: field.label,
            labelPosition: 'top',
            min: 0,
            precision: 2,
            validType: field.validators,
            onChange: (newValue, oldValue) => {
                if (field.onChange) {
                    field.onChange(newValue, oldValue, field);
                }
            },
            readonly: field.readonly,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSelectInput(field) {
        let input = $('#' + field.name);
        input.combobox({
            width: '100%',
            minWidth: 200,
            label: field.label,
            labelPosition: 'top',
            data: field.data,
            valueField: field.valueField || 'value',
            textField: field.textField || 'text',
            panelHeight: 200,
            limitToList: true,
            onSelect: (record) => {
                if (field.onSelect) {
                    field.onSelect(record, field);
                }
            },
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildEnumInput(field) {
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

    buildEntityInput(field) {
        let input = $('#' + field.name);
        input.combobox({
            width: '100%',
            minWidth: 200,
            label: field.label,
            labelPosition: 'top',
            url: field.url,
            method: 'get',
            valueField: field.valueField,
            textField: field.textField,
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

    getRowFormFieldsByIndex(rowIndex: number): any {
        let formFields = {};
        for (let key in this.formFields) {
            let index = Number(key.split('-')[1]);
            if (index == rowIndex) {
                formFields[key] = this.formFields[key];
            }
        }
        return formFields;
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

    add(route = null) {
        let detailTableBody = document.getElementById('detailTableBody');
        if (detailTableBody) {
            detailTableBody.innerHTML = '';
        }
        this.rowIndex = 0;
        this.form.form('clear');
        this.route = this.options.routes.ADD || route;

        this.fields.forEach(field => {
            if (field.defaultValue === null) {
                return;
            }
            switch (field.type) {
                case FormType.TEXT:
                    this.getField(field.name).textbox('setValue', field.defaultValue);
                    break;
                case FormType.DATE:
                    this.getField(field.name).datebox('setValue', field.defaultValue);
                    break;
                case FormType.ENTITY:
                    this.getField(field.name).combobox('select', field.defaultValue);
                    break;
                case FormType.SWITCH:
                    this.getField(field.name).switchbutton('check');
                    break;
            }
        });
    }

    edit(route = null, row) {
        this.fields.forEach(field => {
            if (field.type === FormType.PASSWORD) {
                row[field.name] = '';
            }
        });
        this.form.form('load', row);
        this.route = (this.options.routes.EDIT || route) + row[this.getIdField()];
    }

    submit() {
        this.form.form('submit', {
            url: this.route,
        });
    }
}