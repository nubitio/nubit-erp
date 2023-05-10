import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";

export default class Specialty {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/specialties';

    static readonly EVENTS = {
        ADD: 'specialty:add',
        EDIT: 'specialty:edit',
        DELETE: 'specialty:delete',
        SAVE: 'specialty:save',
        CANCEL: 'specialty:cancel',
        SUCCESS: 'specialty:success',
        LOADING: 'specialty:loading',
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
            .label('Nombre')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('description')
            .label('Descripción')
            .width(100)
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Espacialidades',
            url: Specialty.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Specialty.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Specialty.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Specialty.API_URL,
            events: Specialty.EVENTS,
        });
    }
}