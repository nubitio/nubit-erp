import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";

export default class Physician {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/physicians';

    static readonly EVENTS = {
        ADD: 'physician:add',
        EDIT: 'physician:edit',
        DELETE: 'physician:delete',
        SAVE: 'physician:save',
        CANCEL: 'physician:cancel',
        SUCCESS: 'physician:success',
        LOADING: 'physician:loading',
    }

    constructor(container: string) {
        this.container = container;
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
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('employee')
            .label('Empleado')
            .width(100)
            .url('api/employees')
            .valueField('id')
            .textField('full_name')
            .formatter((value: any) => value?.first_name + ' ' + value?.last_name)
            .valueType('number')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('specialty')
            .label('Especialidad')
            .url('api/specialties')
            .valueField('id')
            .textField('name')
            .formatter((value: any) => value?.name)
            .valueType('number')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('licence')
            .label('Licencia')
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Médicos',
            url: Physician.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Physician.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Physician.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Physician.API_URL,
            events: Physician.EVENTS,
        });
    }
}