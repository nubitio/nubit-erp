import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {FieldBuilder} from "../core/field/FieldBuilder";
import Customer from "./Customer";
import {router} from "../core/router/Router";
import {messenger} from "../core/utils/Messenger";

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
    }

    init() {
        this.fields.push(new FieldBuilder()
            .isIdentity(true)
            .visible(false)
            .type(FieldType.NONE)
            .name('id')
            .label('Id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.NONE)
            .name('customer_id')
            .label('Customer id')
            .required(true)
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENUM)
            .name('type_id')
            .label('Tipo')
            .data([
                {value: 1, text: 'Particular'},
                {value: 2, text: 'Asegurado'},
            ])
            .formatter((value: any) => {
                switch (value) {
                    case 1:
                        return 'Particular';
                    case 2:
                        return 'Asegurado';
                    default:
                        return '';
                }
            })
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('insurance_provider')
            .label('Aseguradora')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('medical_record_number')
            .label('Nro. Historia Clínica')
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
            toolbar: [{
                text: 'Historia Clínica',
                iconCls: 'fas fa-1x fa-file-medical text-info',
                handler: () => {
                    let selected = this.dataGrid.selectedRow;

                    if (!selected) {
                        messenger.show('Info', 'Debe seleccionar un registro');
                    }

                    router.navigate('/medical-record/' + selected.id);
                }
            }]
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