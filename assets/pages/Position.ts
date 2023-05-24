import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {Constants} from "../core/common/Constants";

export default class Position {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = Constants.API_BASE_URL + 'positions';

    static readonly EVENTS = {
        ADD: 'position:add',
        EDIT: 'position:edit',
        DELETE: 'position:delete',
        SAVE: 'position:save',
        CANCEL: 'position:cancel',
        SUCCESS: 'position:success',
        LOADING: 'position:loading',
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
            .valueType('number')
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('department')
            .label('Department')
            .url(Constants.API_BASE_URL + 'departments')
            .valueField('id')
            .textField('name')
            .formatter((value: any) => value?.name)
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
            .build())
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'Position',
            url: Position.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: Position.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: Position.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: Position.API_URL,
            events: Position.EVENTS,
        });
    }
}
