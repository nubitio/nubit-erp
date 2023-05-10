import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";

export default class Department {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/departments';

    static readonly EVENTS = {
        ADD: 'department:add',
        EDIT: 'department:edit',
        DELETE: 'department:delete',
        SAVE: 'department:save',
        CANCEL: 'department:cancel',
        SUCCESS: 'department:success',
        LOADING: 'department:loading',
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
            .type(FieldType.TEXT)
            .name('name')
            .label('Name')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('description')
            .label('Description')
            .width(100)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Department',
            url: Department.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Department.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Department.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Department.API_URL,
            events: Department.EVENTS,
        });
    }
}