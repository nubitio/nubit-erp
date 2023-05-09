import DataGrid from "../core/datagrid/DataGrid";
import Dialog from "../core/dialog/Dialog";
import Form from "../core/form/Form";
import {FieldType} from "../core/field/FieldType";
import {Field} from "../core/field/Field";
import {FieldBuilder} from "../core/field/FieldBuilder";
import {SharedUtils} from "../core/utils/SharedUtils";

export default class User {
    private readonly container: string;
    private fields: Field[] = [];
    private dataGrid: DataGrid;
    private dialog: Dialog;
    private form: Form;

    static readonly API_URL = 'api/users';

    static readonly EVENTS = {
        ADD: 'user:add',
        EDIT: 'user:edit',
        DELETE: 'user:delete',
        SAVE: 'user:save',
        CANCEL: 'user:cancel',
        SUCCESS: 'user:success',
        LOADING: 'user:loading',
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
            .name('usuario_id')
            .label('Usuario id')
            .required(true)
            .valueType('number')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('username')
            .label('Username')
            .width(100)
            .required(true)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.PASSWORD)
            .name('password')
            .label('Password')
            .visible(false)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.TEXT)
            .name('pin')
            .label('Pin')
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.ENTITY)
            .name('rol')
            .label('Rol')
            .url('api/roles')
            .textField('descripcion')
            .valueField('rol_id')
            .valueType('number')
            .required(true)
            .formatter((value: any) => value.descripcion)
            .build());
        this.fields.push(new FieldBuilder()
            .type(FieldType.SWITCH)
            .name('estado')
            .label('Activo')
            .formatter(SharedUtils.booleanFormatter)
            .defaultValue(true)
            .build());
    };

    render() {
        this.renderDataGrid();
        this.renderDialog();
    }

    renderDataGrid() {
        this.dataGrid = new DataGrid(this.container, this.fields, {
            title: 'User',
            url: User.API_URL,
            method: 'get',
            allowAdd: true,
            allowEdit: true,
            allowDelete: true,
            events: User.EVENTS,
        });
    }

    renderDialog() {
        this.dialog = new Dialog(this.container, {
            events: User.EVENTS,
        });

        this.form = new Form(this.dialog.id, this.fields, {
            url: User.API_URL,
            events: User.EVENTS,
        });
    }
}