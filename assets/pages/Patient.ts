import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {FieldBuilder} from "../core/field/FieldBuilder";
import Customer from "./Customer";

export default class Patient extends Customer {
    protected readonly container: string;
    declare dataGrid: DataGrid;
    declare dialog: Dialog;
    declare form: Form;

    static readonly API_URL: string = 'api/patients';

    static readonly EVENTS = {
        ADD: 'patient:add',
        EDIT: 'patient:edit',
        DELETE: 'patient:delete',
        SAVE: 'patient:save',
        CANCEL: 'patient:cancel',
        SUCCESS: 'patient:success',
        LOADING: 'patient:loading',
    }

    constructor(container: string) {
        super(container);
        this.container = container;
        super.init();
        this.init();
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('customer_id')
            .label('Customer id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NUMBER)
            .name('type_id')
            .label('Type id')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('insurance_provider')
            .label('Insurance provider')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('medical_record_number')
            .label('Medical record number')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Patient',
            url: Patient.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Patient.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Patient.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Patient.API_URL,
            events: Patient.EVENTS,
        });
    }
}