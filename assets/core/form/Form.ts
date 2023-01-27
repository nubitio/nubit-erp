import {FormType} from "./FormType";
import {SharedUtils} from "../utils/SharedUtils";
import {Field} from "./Field";
import {DateUtils} from "../utils/DateUtils";
import {bus} from "../event/EventBus";
import {Event} from "../event/Event";
import {httpClient} from "../utils/HttpClient";
import NumberUtils from "../utils/NumberUtils";

export default class Form {
    private container: HTMLElement;
    private readonly fields: Field[];
    private readonly detailFields: Field[];
    private options: any = {};
    private form: JQuery;
    private readonly formFields: any = {};
    private route: null;
    private operation: string;
    private rowIndex = 0;
    private randomId: string = SharedUtils.randomId();
    private events: any = {};

    static readonly EVENTS = {
        ROW_ADDED: 'on:row:added',
        ROW_REMOVED: 'on:row:removed',
    }
    private static readonly OPERATIONS = {
        ADD: 'add',
        EDIT: 'edit',
    };

    constructor(container: string, fields: Field[], options: any = {}) {
        this.container = document.getElementById(container) || document.body;
        this.fields = fields;
        this.detailFields = options.detailFields || [];
        this.options = options || {};
        this.formFields = {};
        this.route = null;
        this.events = options.events;
        this.init();
    }

    init() {
        this.setupView();
        this.buildFormFields(this.fields);

        let ref = this;
        this.form.form({
            iframe: false,
            onSubmit: function () {
                let valid = ref.validate();
                if (valid) {
                    bus.emit(new Event(ref.events.LOADING, true));
                    if (ref.options.url) {
                        if (ref.operation === Form.OPERATIONS.ADD) {
                            ref.addRecord();
                        }
                        if (ref.operation === Form.OPERATIONS.EDIT) {
                            ref.editRecord();
                        }
                        return false;
                    }
                }
                return valid;
            },
            success: function (result: any) {
                try {
                    result = JSON.parse(result);
                    if (result.success) {
                        bus.emit(new Event(ref.events.SUCCESS, result.data || {}));
                        SharedUtils.showMessage('Info', result.msg);
                    } else {
                        SharedUtils.showMessage('Error', result.msg);
                    }
                } catch (e) {
                    SharedUtils.showMessage('Error', 'Error al procesar la respuesta del servidor');
                } finally {
                    bus.emit(new Event(ref.events.LOADING, false));
                }
            }
        });

        this.setupObservers();
    }

    setupView() {
        let form = document.createElement('form');
        form.classList.add('fm');
        form.setAttribute('id', 'form' + this.randomId);
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

            let rowContainer = document.createElement('div');
            rowContainer.classList.add('row');
            leftCol.appendChild(rowContainer);

            let rightCol = document.createElement('div');
            rightCol.classList.add('col-md-8');
            row.appendChild(rightCol);

            this.buildFields(rowContainer, this.fields);

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
            detailTable.setAttribute('id', 'detailTable' + this.randomId);
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
            tableBody.setAttribute('id', 'detailTableBody' + this.randomId);
            detailTable.appendChild(tableBody);
        } else {
            this.buildFields(form, this.fields);
        }
    }

    private addRow() {
        this.rowIndex++;
        let detailTableBody = document.getElementById('detailTableBody' + this.randomId);

        if (!detailTableBody) {
            return;
        }

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
        bus.emit(new Event(Form.EVENTS.ROW_ADDED, this.rowIndex));
    }

    private removeRow(rowIndex: string) {
        let row = document.getElementById('detailRow' + rowIndex);

        if (!row) {
            return;
        }

        row.parentNode?.removeChild(row);
        for (let key in this.formFields) {
            let index = key.split('-')[1];
            if (index == rowIndex) {
                delete this.formFields[key];
            }
        }
        bus.emit(new Event(Form.EVENTS.ROW_REMOVED, rowIndex));
    }

    private buildFields(container: HTMLElement, fields: Field[], containerType = 'div', rowIndex: number | null = null) {
        fields.forEach(field => {
            if (field.type === FormType.NONE) {
                if (field.isIdentity && this.options.url) {
                    let input: HTMLInputElement = document.createElement('input');
                    input.setAttribute('type', 'hidden');
                    input.setAttribute('name', field.name);
                    container.appendChild(input);
                }
                return;
            }

            let fieldContainer = document.createElement(containerType);
            if (containerType === 'div' && !field.hidden) {
                fieldContainer.setAttribute('class', 'mb-2 col-md-' + field.col);
            }

            let input: HTMLElement;
            if (field.type !== FormType.SUBMIT) {
                input = document.createElement('input');
                if (containerType === 'td') {
                    if (rowIndex == null) {
                        return;
                    }
                    let index = rowIndex - 1;
                    input.setAttribute('name', `details[${index}][${field.name}]`);
                } else {
                    input.setAttribute('name', field.name);
                }
            } else {
                input = document.createElement('a');
                input.setAttribute('class', 'md-btn accent mt-4');
            }
            input.setAttribute('id', field.name + (rowIndex ? '-' + rowIndex : ''));
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
                if (rowIndex == null) {
                    return;
                }
                this.removeRow(rowIndex.toString());
            });
            fieldContainer.appendChild(removeBtn);
            container.appendChild(fieldContainer);
        }
    }

    setupObservers() {
        bus.on(
            event => event.name === this.events?.ADD,
            () => {
                this.add();
                this.operation = Form.OPERATIONS.ADD;
            }
        )

        bus.on(
            event => event.name === this.events?.EDIT,
            (event) => {
                this.edit(null, event.data.row);
                this.operation = Form.OPERATIONS.EDIT;
            }
        )

        bus.on(
            event => event.name === this.events?.SAVE,
            () => {
                if (!this.options.preventDefault) {
                    this.submit();
                }
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
                case FormType.SUBMIT:
                    this.buildSubmitButton(field);
                    break;
                default:
                    break;
            }
        });
    }

    buildFileInput(field: Field) {
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

    buildTextInput(field: Field) {
        let input = $('#' + field.name);
        input.textbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            validType: field.validators,
            required: field.required,
        });
        if (field.hidden) {
            input.textbox('hide');
        }
        this.formFields[field.name] = input;
    }

    buildPasswordInput(field: Field) {
        let input = $('#' + field.name);
        input.passwordbox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildTextAreaInput(field: Field) {
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

    buildDateInput(field: Field) {
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
            onChange: (newValue: number, oldValue: number) => {
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
            onChange: (newValue: number, oldValue: number) => {
                if (field.onChange) {
                    field.onChange(newValue, oldValue, field);
                }
            },
            readonly: field.readonly,
            disabled: field.disabled,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSelectInput(field: Field) {
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
            onSelect: (record: any) => {
                if (field.onSelect) {
                    field.onSelect(record, field);
                }
            },
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildEnumInput(field: Field) {
        let input = $('#' + field.name);
        input.combobox({
            width: '100%',
            label: field.label,
            labelPosition: 'top',
            data: field.data,
            panelHeight: 'auto',
            limitToList: true,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildEntityInput(field: Field) {
        let input = $('#' + field.name);
        input.combobox({
            width: '100%',
            minWidth: 200,
            label: field.label,
            labelPosition: 'top',
            url: field.url,
            method: 'get',
            mode: field.mode ?? 'local',
            valueField: field.valueField,
            textField: field.textField,
            panelHeight: 200,
            limitToList: true,
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSwitchInput(field: Field) {
        let input = $('#' + field.name);
        input.switchbutton({
            label: field.label,
            labelPosition: 'top',
            onText: "SI",
            offText: "NO",
            value: this.options.url ? 'true' : '1',
            required: field.required,
        });
        this.formFields[field.name] = input;
    }

    buildSubmitButton(field: Field) {
        let button = $('#' + field.name);
        button.linkbutton({
            width: '100%',
            height: 40,
            text: field.label,
            onClick: () => {
                if (this.validate()) {
                    if (field.onClick) {
                        field.onClick();
                    } else {
                        this.submit();
                    }
                }
            }
        });
        this.formFields[field.name] = button;
    }

    getDetailFormFields(): any {
        let formFields: Record<string, any> = {};
        for (let key in this.formFields) {
            let index = Number(key.split('-')[1]);
            if (index > 0) {
                formFields.key = this.formFields[key];
            }
        }
        return formFields;
    }

    getRowFormFieldsByIndex(rowIndex: number): any {
        let formFields: Record<string, any> = {};
        for (let key in this.formFields) {
            let index = Number(key.split('-')[1]);
            if (index == rowIndex) {
                formFields[key] = this.formFields[key];
            }
        }
        return formFields;
    }

    getIdField() {
        let idField = this.fields.find(field => field.isIdentity);
        return idField?.name ?? '';
    }

    /**
     * @deprecated use getField instead
     */
    get field() {
        return this.formFields || {};
    }

    getField(name: string): JQuery {
        return this.formFields[name] || {};
    }

    add(route = null) {
        let detailTableBody = document.getElementById('detailTableBody' + this.randomId);
        if (detailTableBody) {
            detailTableBody.innerHTML = '';
        }
        this.rowIndex = 0;
        this.form.form('clear');
        this.route = this.options.routes?.ADD || route;

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

    edit(route = null, row: any) {
        this.fields.forEach(field => {
            if (field.type === FormType.PASSWORD) {
                row[field.name] = '';
            }
        });
        this.form.form('load', row);
        this.route = (this.options.routes?.EDIT || route) + row[this.getIdField()];
    }

    validate() {
        return this.form.form('validate');
    }

    submit() {
        this.form.form('submit', {
            url: this.route,
        });
    }

    getData() {
        return this.form.serialize();
    }

    getDataArray() {
        return this.form.serializeArray();
    }

    getDataObject() {
        let data = this.getDataArray();
        let object: Record<string, any> = {};
        data.forEach(item => {
            if (item.name.includes('[')) {
                let name = item.name.split('[')[0];
                let index = Number(item.name.split('[')[1].split(']')[0]);
                let fieldName = item.name.split('[')[2].split(']')[0];
                if (!object[name]) {
                    object[name] = [];
                }
                if (!object[name][index]) {
                    object[name][index] = {};
                }
                object[name][index][fieldName] = this.getTypedFormValue(item);
            } else {
                object[item.name] = this.getTypedFormValue(item);
            }
        });
        return object;
    }

    getTypedFormValue(item: JQuery.NameValuePair): any {
        let field = this.fields.find(field => field.name === item.name);
        if (!field) {
            let fieldName = item.name.split('[')[2].split(']')[0];
            field = this.detailFields.find(field => field.name === fieldName);
        }
        let value: any = item.value;
        switch (field?.type) {
            case FormType.NUMBER:
            case FormType.CURRENCY:
                value = Number(value);
                break;
            case FormType.ENTITY:
                let id = field.valueField || 'id';
                if (value === '') {
                    value = null;
                }
                if (field.valueType === 'number') {
                    value = Number(value);
                }
                if (value !== null) {
                    value = {[id]: value};
                }
                break;
            case FormType.ENUM:
                if (NumberUtils.isNumber(value)) {
                    value = Number(value);
                }
                break;
            case FormType.TEXT:
                value = value.trim();
                if (value === '') {
                    value = null;
                }
                break;
            case FormType.SWITCH:
                value = value === 'true';
                break;
            default:
                break;
        }

        if (field?.isIdentity) {
            if (value === '') {
                value = null;
            }
            if (field.valueType === 'number') {
                value = Number(value);
            }
        }

        return value;
    }

    clear() {
        this.form.form('clear');
    }

    reset() {
        this.form.form('reset');
    }

    private addRecord() {
        httpClient.post<any>(this.options.url, this.getDataObject())
            .subscribe(
                (response: any) => {
                    bus.emit(new Event(this.events.SUCCESS, response));
                }
            )
            .add(() => {
                bus.emit(new Event(this.events.LOADING, false));
            })
    }

    private editRecord() {
        let row = this.getDataObject();
        let id = row[this.getIdField()];
        httpClient.put<any>(this.options.url + '/' + id, row)
            .subscribe(
                (response: any) => {
                    bus.emit(new Event(this.events.SUCCESS, response));
                }
            )
            .add(() => {
                bus.emit(new Event(this.events.LOADING, false));
            })
    }
}